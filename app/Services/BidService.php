<?php

namespace App\Services;

use App\Enums\BidStatus;
use App\Enums\VendorStatus;
use App\Models\Bid;
use App\Models\Tender;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

/**
 * Manages bid lifecycle: draft creation, pricing, submission, withdrawal.
 */
class BidService
{
    public function __construct(
        private BidSealingService $sealingService,
    ) {}

    /**
     * Create a new draft bid for a vendor on a tender.
     *
     * @param  Tender  $tender  The tender to bid on.
     * @param  Vendor  $vendor  The vendor creating the bid.
     * @return Bid The newly created draft bid.
     *
     * @throws \RuntimeException If submission is not allowed.
     */
    public function createDraft(Tender $tender, Vendor $vendor): Bid
    {
        $this->validateSubmissionAllowed($tender, $vendor);

        return Bid::create([
            'tender_id' => $tender->id,
            'vendor_id' => $vendor->id,
            'bid_reference' => $tender->reference_number.'-B'.str_pad($tender->bids()->count() + 1, 3, '0', STR_PAD_LEFT),
            // Legacy column from a pre-BUG-18 design that tried to model two-envelope
            // bids as multiple bid rows. We now keep one bid row per (tender, vendor)
            // (DB unique constraint, BUG-19) and the envelope split lives entirely on
            // bid_documents.envelope_type. Always 'single' here regardless of
            // tender->is_two_envelope. Don't read this column elsewhere.
            'envelope_type' => 'single',
            'status' => BidStatus::Draft,
            'currency' => $tender->currency,
            'is_sealed' => false,
        ]);
    }

    /**
     * Update BOQ pricing for a draft bid.
     *
     * @param  Bid  $bid  The draft bid to update.
     * @param  array  $boqPrices  Array of pricing items, each with boq_item_id, unit_price, total_price, and optional remarks.
     */
    public function updatePricing(Bid $bid, array $boqPrices): void
    {
        DB::transaction(function () use ($bid, $boqPrices) {
            foreach ($boqPrices as $price) {
                $bid->boqPrices()->updateOrCreate(
                    ['boq_item_id' => $price['boq_item_id']],
                    [
                        'unit_price' => $price['unit_price'],
                        'total_price' => $price['total_price'],
                        'remarks' => $price['remarks'] ?? null,
                    ]
                );
            }
        });
    }

    /**
     * Submit a bid — validates completeness and seals it.
     *
     * @param  Bid  $bid  The draft bid to submit.
     *
     * @throws \RuntimeException If not all BOQ items are priced or any price is invalid.
     */
    public function submit(Bid $bid): void
    {
        // Verify all BOQ items have pricing
        $tender = $bid->tender;
        $totalBoqItems = $tender->boqSections()->withCount('items')->get()->sum('items_count');
        $pricedItems = $bid->boqPrices()->count();

        if ($pricedItems < $totalBoqItems) {
            throw new \RuntimeException("All BOQ items must be priced. {$pricedItems}/{$totalBoqItems} priced.");
        }

        // Verify all unit_prices > 0
        if ($bid->boqPrices()->where('unit_price', '<=', 0)->exists()) {
            throw new \RuntimeException('All unit prices must be greater than zero.');
        }

        // Two-envelope tenders require at least one technical document. The
        // financial envelope is implicitly satisfied by the BOQ pricing above
        // (Sub-phase A treats financial documents as optional). BUG-18.
        if ($tender->is_two_envelope) {
            $hasTechnicalDoc = $bid->documents()
                ->where('envelope_type', 'technical')
                ->exists();
            if (! $hasTechnicalDoc) {
                throw new \RuntimeException(__('messages.bid.technical_envelope_required'));
            }
        }

        $this->sealingService->sealBid($bid);
    }

    /**
     * Withdraw a submitted bid before deadline.
     *
     * @param  Bid  $bid  The bid to withdraw.
     * @param  string  $reason  The reason for withdrawal.
     *
     * @throws \RuntimeException If submission deadline has passed.
     */
    public function withdraw(Bid $bid, string $reason): void
    {
        if (! $bid->tender->is_open_for_submission) {
            throw new \RuntimeException('Cannot withdraw after submission deadline.');
        }

        $bid->update([
            'status' => BidStatus::Withdrawn,
            'withdrawal_reason' => $reason,
        ]);
    }

    /**
     * Validate that a vendor is allowed to submit a bid on this tender.
     *
     * @param  Tender  $tender  The tender to validate against.
     * @param  Vendor  $vendor  The vendor to validate.
     *
     * @throws \RuntimeException If any validation check fails.
     */
    public function validateSubmissionAllowed(Tender $tender, Vendor $vendor): void
    {
        if (! $tender->is_open_for_submission) {
            throw new \RuntimeException(__('messages.bid.tender_closed'));
        }

        if ($vendor->prequalification_status !== VendorStatus::Qualified) {
            throw new \RuntimeException(__('messages.bid.vendor_not_qualified'));
        }

        if (! $vendor->is_active) {
            throw new \RuntimeException(__('messages.bid.vendor_not_active'));
        }

        $matchingCategories = $vendor->categories()
            ->whereIn('categories.id', $tender->categories()->pluck('categories.id'))
            ->exists();

        if (! $matchingCategories) {
            throw new \RuntimeException(__('messages.bid.category_mismatch'));
        }

        // Bid submission is final — once a (tender_id, vendor_id) row exists
        // in any state (draft, submitted, withdrawn, opened, etc.) the
        // vendor cannot start another bid for that tender. Mirrors the DB
        // unique constraint at migrations/...000018_create_bids_table.php.
        // Without this guard the INSERT in createDraft() trips SQLSTATE 23000
        // on the withdraw → start-bid path (BUG-19).
        $existingBid = $tender->bids()
            ->where('vendor_id', $vendor->id)
            ->exists();

        if ($existingBid) {
            throw new \RuntimeException(__('messages.bid.duplicate'));
        }
    }
}

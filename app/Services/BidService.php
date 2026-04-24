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
            'envelope_type' => $tender->is_two_envelope ? 'technical' : 'single',
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

        // Check if vendor already has an active bid
        $existingBid = $tender->bids()
            ->where('vendor_id', $vendor->id)
            ->whereNotIn('status', [BidStatus::Withdrawn->value])
            ->exists();

        if ($existingBid) {
            throw new \RuntimeException(__('messages.bid.duplicate'));
        }
    }
}

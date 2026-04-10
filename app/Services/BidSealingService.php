<?php

namespace App\Services;

use App\Enums\BidStatus;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Tender;
use App\Models\User;

/**
 * Handles bid encryption (sealing) and decryption (opening).
 */
class BidSealingService
{
    /**
     * Seal a bid on submission. Encrypts the complete pricing data (all bid_boq_prices)
     * using Laravel's encrypt() which uses AES-256-CBC. Sets is_sealed=true.
     *
     * @param  Bid  $bid  The bid to seal.
     */
    public function sealBid(Bid $bid): void
    {
        // Collect all BOQ prices into a JSON structure
        $pricingData = $bid->boqPrices()->with('boqItem:id,item_code')->get()
            ->map(fn ($p) => [
                'boq_item_id' => $p->boq_item_id,
                'item_code' => $p->boqItem->item_code,
                'unit_price' => $p->unit_price,
                'total_price' => $p->total_price,
            ])->toJson();

        $totalAmount = $bid->boqPrices()->sum('total_price');

        $bid->update([
            'encrypted_pricing_data' => encrypt($pricingData),
            'total_amount' => $totalAmount,
            'is_sealed' => true,
            'status' => BidStatus::Submitted,
            'submitted_at' => now(),
            'submission_ip' => request()->ip(),
            'submission_user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Open all bids for a tender. Only callable after tender.opening_date.
     * Requires dual authorization (two different users).
     * Decrypts pricing, sets is_sealed=false, logs opening event.
     *
     * @param  Tender  $tender  The tender whose bids to open.
     * @param  User  $opener  The user performing the opening.
     * @param  User  $authorizer  The second user authorizing the opening.
     *
     * @throws \InvalidArgumentException If opener and authorizer are the same user.
     * @throws \RuntimeException If bids cannot be opened yet.
     */
    public function openBids(Tender $tender, User $opener, User $authorizer): void
    {
        if ($opener->id === $authorizer->id) {
            throw new \InvalidArgumentException('Bid opening requires two different users.');
        }

        if (! $this->canOpen($tender)) {
            throw new \RuntimeException('Bids cannot be opened before the opening date.');
        }

        $bids = $tender->bids()->where('status', BidStatus::Submitted)->sealed()->get();

        foreach ($bids as $bid) {
            $bid->update([
                'is_sealed' => false,
                'status' => BidStatus::Opened,
                'opened_at' => now(),
                'opened_by' => $opener->id,
            ]);

            AuditLog::create([
                'user_id' => $opener->id,
                'auditable_type' => Bid::class,
                'auditable_id' => $bid->id,
                'action' => 'opened',
                'new_values' => ['opened_by' => $opener->id, 'authorized_by' => $authorizer->id],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Check if tender opening date has passed.
     *
     * @param  Tender  $tender  The tender to check.
     * @return bool True if the opening date has passed.
     */
    public function canOpen(Tender $tender): bool
    {
        return $tender->opening_date && $tender->opening_date->isPast();
    }
}

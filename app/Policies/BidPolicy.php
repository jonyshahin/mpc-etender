<?php

namespace App\Policies;

use App\Enums\BidStatus;
use App\Enums\VendorStatus;
use App\Models\Bid;
use App\Models\BidDocument;
use App\Models\Tender;
use App\Models\User;
use App\Models\Vendor;

class BidPolicy
{
    /**
     * MPC user assigned to the bid's project, OR the vendor who owns the bid.
     */
    public function view(User|Vendor $actor, Bid $bid): bool
    {
        if ($actor instanceof Vendor) {
            return $actor->id === $bid->vendor_id;
        }

        return $actor->isAssignedToProject($bid->tender->project_id);
    }

    /**
     * Pricing is visible only after the tender opening date has passed and the bid is unsealed.
     */
    public function viewPricing(User|Vendor $actor, Bid $bid): bool
    {
        if (! $this->view($actor, $bid)) {
            return false;
        }

        return $bid->tender->opening_date->isPast() && ! $bid->is_sealed;
    }

    /**
     * Only qualified, active vendors in a matching category can create bids
     * on tenders that are open for submission.
     */
    public function create(Vendor $vendor, Tender $tender): bool
    {
        return $vendor->prequalification_status === VendorStatus::Qualified
            && $vendor->is_active
            && $tender->is_open_for_submission
            && $vendor->categories()
                ->whereIn('categories.id', $tender->categories()->pluck('categories.id'))
                ->exists();
    }

    /**
     * Owner can edit pricing while the bid is a draft and the tender accepts submissions.
     */
    public function update(Vendor $vendor, Bid $bid): bool
    {
        return $vendor->id === $bid->vendor_id
            && $bid->status === BidStatus::Draft
            && $bid->tender->is_open_for_submission;
    }

    /**
     * Same gate as update — submission only before deadline, only on a draft owned by this vendor.
     */
    public function submit(Vendor $vendor, Bid $bid): bool
    {
        return $vendor->id === $bid->vendor_id
            && $bid->status === BidStatus::Draft
            && $bid->tender->is_open_for_submission;
    }

    /**
     * Withdraw is only valid for an already-submitted bid before the deadline.
     * After deadline, sealed bids cannot be withdrawn.
     */
    public function withdraw(Vendor $vendor, Bid $bid): bool
    {
        return $vendor->id === $bid->vendor_id
            && $bid->status === BidStatus::Submitted
            && $bid->tender->is_open_for_submission;
    }

    /**
     * Vendor can attach / remove documents while the bid is a draft and the
     * tender accepts submissions. Mirrors update() for pricing.
     */
    public function manageDocuments(Vendor $vendor, Bid $bid): bool
    {
        return $vendor->id === $bid->vendor_id
            && $bid->status === BidStatus::Draft
            && $bid->tender->is_open_for_submission;
    }

    /**
     * Vendor can view (download) any document on their own bid at any status —
     * they're the owner. Phase A scope.
     *
     * Signature is (vendor, bid, doc) — Gate dispatches by the second arg's
     * class (Bid), and the controller passes the document as the extra arg.
     *
     * TODO(BUG-20): extend to evaluators with phase-aware gating —
     * technical envelope readable during technical evaluation, financial
     * envelope readable only after financial opening. Will need an actor
     * union type (Vendor|User) and dispatch on $doc->envelope_type +
     * tender's two opening dates (which BUG-20 introduces).
     */
    public function viewDocument(Vendor $vendor, Bid $bid, BidDocument $doc): bool
    {
        return $vendor->id === $bid->vendor_id && $doc->bid_id === $bid->id;
    }
}

<?php

namespace App\Policies;

use App\Enums\VendorStatus;
use App\Models\Bid;
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
     * Only qualified vendors in a matching category can create bids.
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
     * Submission only before the tender deadline.
     */
    public function submit(Vendor $vendor, Bid $bid): bool
    {
        return $vendor->id === $bid->vendor_id
            && $bid->tender->is_open_for_submission;
    }
}

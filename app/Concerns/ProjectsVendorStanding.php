<?php

namespace App\Concerns;

use App\Models\Vendor;

/**
 * Where a vendor stands with MPC, in the one shape both screens render.
 *
 * The dashboard and the profile answered this question differently. The
 * dashboard compared `prequalification_status` against the string literals
 * 'pending' and 'rejected', so a suspended, blacklisted or under-review vendor
 * saw no warning at all; the profile — once it said anything — derived the
 * same answer from the enum. Sharing the projection is what stops them
 * drifting apart again.
 */
trait ProjectsVendorStanding
{
    /**
     * @return array{
     *     status: string,
     *     status_label_key: string,
     *     qualified_at: mixed,
     *     can_bid: bool,
     *     reason: string|null,
     * }
     */
    protected function vendorStanding(Vendor $vendor): array
    {
        $status = $vendor->prequalification_status;
        $canBid = $vendor->canBid();

        return [
            'status' => $status->value,
            'status_label_key' => 'status.'.$status->value,
            'qualified_at' => $vendor->qualified_at,
            'can_bid' => $canBid,
            // Written by an admin rejecting or suspending the account, and
            // meant for the vendor to act on. Withheld while they are in good
            // standing: a stale reason from a since-lifted suspension would
            // read as a current one.
            'reason' => $canBid ? null : $vendor->rejection_reason,
        ];
    }
}

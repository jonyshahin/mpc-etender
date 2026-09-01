<?php

namespace App\Enums;

/**
 * Lifecycle of a request to open a tender's bids.
 *
 * There is deliberately no Expired case. Expiry is a function of
 * `expires_at` and the clock, so it needs no scheduled job to write it and
 * cannot drift out of step with the timestamp it is derived from — a request
 * whose window has passed is Pending-but-not-actionable, never a row someone
 * forgot to sweep.
 */
enum BidOpeningRequestStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function labelKey(): string
    {
        return 'status.'.$this->value;
    }
}

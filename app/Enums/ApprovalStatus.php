<?php

namespace App\Enums;

/**
 * Where an approval request sits in its chain.
 *
 * Single source of truth for the queue's filter tabs, the values that filter
 * accepts and the `approval_requests.status` cast. The queue used to hardcode
 * `where('status', 'pending')` with no filter at all, so the other four states
 * were unreachable from the interface — an approval that escalated or expired
 * left the only screen that lists them and could not be looked up again.
 */
enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Escalated = 'escalated';
    case Expired = 'expired';

    /** Translation key for the human-facing label, shared by every picker. */
    public function labelKey(): string
    {
        return 'status.'.$this->value;
    }

    /** @return array<int, array{value: string, labelKey: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'labelKey' => $case->labelKey()],
            self::cases(),
        );
    }
}

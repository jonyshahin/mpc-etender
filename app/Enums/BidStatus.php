<?php

namespace App\Enums;

/**
 * Where a vendor's submission stands.
 *
 * Single source of truth for the vendor bid list's status filter, its tab
 * labels, and the React side's status comparisons. Those were string literals
 * spread across the filter, the withdrawn/rejected banner and the
 * continue-vs-view button, with nothing to keep them in step with the cases
 * here.
 */
enum BidStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Withdrawn = 'withdrawn';
    case Opened = 'opened';
    case UnderEvaluation = 'under_evaluation';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Disqualified = 'disqualified';

    /** Translation key for the human-facing label, shared by every picker. */
    public function labelKey(): string
    {
        return 'status.'.$this->value;
    }

    /**
     * Still the vendor's to change.
     *
     * Only a draft is editable; everything else is terminal from the vendor's
     * side, submission being final per the product spec.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
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

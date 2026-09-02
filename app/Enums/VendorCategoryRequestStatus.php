<?php

namespace App\Enums;

/**
 * Where a vendor's category change request stands.
 *
 * The five values were string literals everywhere — the migration's enum
 * column, VendorCategoryRequest::scopeOpen's whereIn, and every branch in
 * VendorCategoryRequestService — with nothing keeping them in step.
 */
enum VendorCategoryRequestStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /** Translation key for the human-facing label, shared by every picker. */
    public function labelKey(): string
    {
        return 'status.'.$this->value;
    }

    /** Still with MPC, and still the vendor's to withdraw. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::UnderReview], true);
    }

    /** The states scopeOpen() matches. */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $case) => $case->isOpen()));
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

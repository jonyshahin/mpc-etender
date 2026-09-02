<?php

namespace App\Enums;

/**
 * Where a prequalification document stands with review.
 *
 * `Expired` is written by nothing: the codebase derives expiry from
 * `vendor_documents.expiry_date` — the vendor dashboard's "needs attention"
 * counts do exactly that — and no code path ever sets this case. It stays a
 * case because rows may carry it, but no picker offers it and no filter
 * counts it; {@see App\Http\Controllers\Vendor\DocumentController} derives
 * expiry from the date instead, so a lapsed licence is flagged whatever its
 * review status says.
 */
enum VendorDocStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';

    /** Translation key for the human-facing label, shared by every picker. */
    public function labelKey(): string
    {
        return 'status.'.$this->value;
    }

    /** Whether the vendor may still take this document back. */
    public function isDeletable(): bool
    {
        return $this === self::Pending;
    }

    /**
     * The statuses a vendor filter should offer.
     *
     * Excludes Expired, which nothing writes — a tab reading zero beside
     * documents the page has visibly marked as lapsed would be worse than no
     * tab at all.
     *
     * @return array<int, array{value: string, labelKey: string}>
     */
    public static function options(): array
    {
        return array_values(array_map(
            fn (self $case) => ['value' => $case->value, 'labelKey' => $case->labelKey()],
            array_filter(self::cases(), fn (self $case) => $case !== self::Expired),
        ));
    }
}

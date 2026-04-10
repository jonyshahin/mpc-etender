<?php

namespace App\Models;

use App\Enums\AwardStatus;
use Database\Factories\AwardFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contract award linking a winning bid to a vendor and approver.
 *
 * Relationships: tender, bid, vendor, approvedBy.
 */
class Award extends Model
{
    /** @use HasFactory<AwardFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tender_id',
        'bid_id',
        'vendor_id',
        'approved_by',
        'award_amount',
        'currency',
        'justification',
        'status',
        'letter_file_path',
        'awarded_at',
        'notified_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AwardStatus::class,
            'award_amount' => 'decimal:2',
            'awarded_at' => 'datetime',
            'notified_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

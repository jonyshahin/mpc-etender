<?php

namespace App\Models;

use Database\Factories\VendorCategoryRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorCategoryRequest extends Model
{
    /** @use HasFactory<VendorCategoryRequestFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'vendor_id',
        'justification',
        'status',
        'reviewed_by',
        'reviewer_comments',
        'withdraw_reason',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorCategoryRequestItem::class, 'request_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(VendorCategoryRequestEvidence::class, 'request_id');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeOpen($q)
    {
        return $q->whereIn('status', ['pending', 'under_review']);
    }
}

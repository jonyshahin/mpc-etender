<?php

namespace App\Models;

use App\Enums\BidOpeningRequestStatus;
use Database\Factories\BidOpeningRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The opener's half of a dual-authorised bid opening.
 *
 * Relationships: tender, requester, authorizer, confirmer.
 */
class BidOpeningRequest extends Model
{
    /** @use HasFactory<BidOpeningRequestFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'tender_id',
        'requested_by',
        'authorizer_id',
        'status',
        'requested_at',
        'expires_at',
        'confirmed_at',
        'confirmed_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BidOpeningRequestStatus::class,
            'requested_at' => 'datetime',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Still awaiting confirmation, and still inside its window.
     *
     * Both halves matter: a pending row past expires_at must not be
     * confirmable, and it is the same predicate that decides whether a new
     * request may be raised.
     */
    public function isActionable(): bool
    {
        return $this->status === BidOpeningRequestStatus::Pending
            && $this->expires_at->isFuture();
    }

    public function hasExpired(): bool
    {
        return $this->status === BidOpeningRequestStatus::Pending
            && $this->expires_at->isPast();
    }

    /** @param  Builder<self>  $query */
    public function scopeActionable(Builder $query): Builder
    {
        return $query->where('status', BidOpeningRequestStatus::Pending)
            ->where('expires_at', '>', now());
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorizer_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}

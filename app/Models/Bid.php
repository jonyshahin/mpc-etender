<?php

namespace App\Models;

use App\Enums\BidStatus;
use App\Enums\EnvelopeType;
use Database\Factories\BidFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vendor submission against a tender. Pricing is encrypted at rest until opened.
 *
 * Relationships: tender, vendor, openedBy, boqPrices, documents, evaluationScores.
 */
class Bid extends Model
{
    /** @use HasFactory<BidFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tender_id',
        'vendor_id',
        'bid_reference',
        'envelope_type',
        'encrypted_pricing_data',
        'total_amount',
        'currency',
        'technical_notes',
        'status',
        'is_sealed',
        'submitted_at',
        'opened_at',
        'opened_by',
        'withdrawal_reason',
        'submission_ip',
        'submission_user_agent',
    ];

    protected function casts(): array
    {
        return [
            'envelope_type' => EnvelopeType::class,
            'status' => BidStatus::class,
            'total_amount' => 'decimal:2',
            'is_sealed' => 'boolean',
            'submitted_at' => 'datetime',
            'opened_at' => 'datetime',
        ];
    }

    // ── Encrypted pricing accessor/mutator ──

    public function setEncryptedPricingDataAttribute(?string $value): void
    {
        $this->attributes['encrypted_pricing_data'] = $value ? encrypt($value) : null;
    }

    public function getEncryptedPricingDataAttribute(?string $value): ?string
    {
        return $value ? decrypt($value) : null;
    }

    // ── Relationships ──

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function boqPrices(): HasMany
    {
        return $this->hasMany(BidBoqPrice::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BidDocument::class);
    }

    public function evaluationScores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class);
    }

    // ── Scopes ──

    public function scopeSealed($query)
    {
        return $query->where('is_sealed', true);
    }

    public function scopeOpened($query)
    {
        return $query->where('is_sealed', false);
    }

    public function scopeForTender($query, string $tenderId)
    {
        return $query->where('tender_id', $tenderId);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An officer's scores and gate attestations for one bid, for engine scoring.
 *
 * Relationships: tender, bid, scorer, bankGuaranteeAttester,
 * mandatoryDocumentsAttester.
 */
class EngineBidAssessment extends Model
{
    use HasUuids;

    /** Engine criterion key => column. Only criteria the officer scores. */
    public const SCORES = [
        'technical' => 'technical_score',
        'references' => 'references_score',
        'payment_flexibility' => 'payment_flexibility_score',
    ];

    /** Engine gate key => column. Only gates the officer attests. */
    public const GATES = [
        'bank_guarantee' => 'bank_guarantee',
        'mandatory_documents' => 'mandatory_documents',
    ];

    protected $fillable = [
        'tender_id',
        'bid_id',
        'technical_score',
        'references_score',
        'payment_flexibility_score',
        'scored_by',
        'scored_at',
        'bank_guarantee',
        'bank_guarantee_attested_by',
        'bank_guarantee_attested_at',
        'mandatory_documents',
        'mandatory_documents_attested_by',
        'mandatory_documents_attested_at',
    ];

    protected function casts(): array
    {
        return [
            'technical_score' => 'decimal:2',
            'references_score' => 'decimal:2',
            'payment_flexibility_score' => 'decimal:2',
            'scored_at' => 'datetime',
            'bank_guarantee' => 'boolean',
            'bank_guarantee_attested_at' => 'datetime',
            'mandatory_documents' => 'boolean',
            'mandatory_documents_attested_at' => 'datetime',
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

    public function scorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by');
    }

    public function bankGuaranteeAttester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bank_guarantee_attested_by');
    }

    public function mandatoryDocumentsAttester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mandatory_documents_attested_by');
    }
}

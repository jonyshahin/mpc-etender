<?php

namespace App\Models;

use Database\Factories\ClarificationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vendor question and MPC answer attached to a tender.
 *
 * Relationships: tender, askedBy, answeredBy.
 */
class Clarification extends Model
{
    /** @use HasFactory<ClarificationFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tender_id',
        'asked_by',
        'answered_by',
        'question',
        'answer',
        'is_published',
        'asked_at',
        'answered_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'asked_at' => 'datetime',
            'answered_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function askedBy(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'asked_by');
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }
}

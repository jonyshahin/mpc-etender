<?php

namespace App\Models;

use Database\Factories\EvaluationScoreFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Individual evaluator score for a bid against a criterion.
 *
 * Relationships: bid, criterion, evaluator.
 */
class EvaluationScore extends Model
{
    /** @use HasFactory<EvaluationScoreFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'bid_id',
        'criterion_id',
        'evaluator_id',
        'score',
        'justification',
        'scored_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'scored_at' => 'datetime',
        ];
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriterion::class, 'criterion_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}

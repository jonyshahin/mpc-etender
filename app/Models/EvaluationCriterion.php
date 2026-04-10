<?php

namespace App\Models;

use Database\Factories\EvaluationCriterionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Weighted scoring criterion for a tender, grouped by envelope.
 *
 * Relationships: tender, scores.
 */
class EvaluationCriterion extends Model
{
    /** @use HasFactory<EvaluationCriterionFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'evaluation_criteria';

    protected $fillable = [
        'tender_id',
        'name_en',
        'name_ar',
        'envelope',
        'weight_percentage',
        'max_score',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'weight_percentage' => 'decimal:2',
            'max_score' => 'decimal:2',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class, 'criterion_id');
    }
}

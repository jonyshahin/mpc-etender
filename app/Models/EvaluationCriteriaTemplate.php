<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * MPC's standard engine scoring criteria — the starting point every new
 * tender copies. See the migration for why weights are columns.
 */
class EvaluationCriteriaTemplate extends Model
{
    use HasUuids;

    public const STANDARD = 'mpc_standard';

    /** The five criteria eTender's engine schema scores, in display order. */
    public const WEIGHTS = [
        'price' => 'weight_price',
        'technical' => 'weight_technical',
        'timeline' => 'weight_timeline',
        'references' => 'weight_references',
        'payment_flexibility' => 'weight_payment_flexibility',
    ];

    protected $fillable = [
        'key',
        'name',
        'weight_price',
        'weight_technical',
        'weight_timeline',
        'weight_references',
        'weight_payment_flexibility',
        'gate_bank_guarantee',
        'gate_mandatory_documents',
        'margin_below',
    ];

    protected function casts(): array
    {
        return [
            'weight_price' => 'integer',
            'weight_technical' => 'integer',
            'weight_timeline' => 'integer',
            'weight_references' => 'integer',
            'weight_payment_flexibility' => 'integer',
            'gate_bank_guarantee' => 'boolean',
            'gate_mandatory_documents' => 'boolean',
            'margin_below' => 'decimal:2',
        ];
    }

    public static function standard(): self
    {
        return static::where('key', self::STANDARD)->firstOrFail();
    }
}

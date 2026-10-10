<?php

namespace App\Models;

use App\Enums\EngineEvaluationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One batch submitted to the engine and, once it finalises, its result.
 *
 * Relationships: tender, profile, submitter.
 */
class EngineEvaluation extends Model
{
    use HasUuids;

    protected $fillable = [
        'tender_id',
        'tender_engine_profile_id',
        'batch_id',
        'idempotency_key',
        'supersedes_batch_id',
        'request',
        'status',
        'result',
        'submitted_by',
        'submitted_at',
        'finalised_at',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'request' => 'array',
            'status' => EngineEvaluationStatus::class,
            'result' => 'array',
            'submitted_at' => 'datetime',
            'finalised_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * A finalised result never changes — the engine's rule for its own batches
     * (DECISIONS §13.2), held here for eTender's copy. Only the bookkeeping
     * timestamp may move.
     */
    protected static function booted(): void
    {
        static::updating(function (self $evaluation) {
            $original = $evaluation->getOriginal('status');
            $wasFinal = $original instanceof EngineEvaluationStatus && $original->isFinal();

            if ($wasFinal && array_diff(array_keys($evaluation->getDirty()), ['last_checked_at', 'updated_at']) !== []) {
                throw new LogicException("Engine evaluation {$evaluation->id} is finalised and cannot be changed.");
            }
        });
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(TenderEngineProfile::class, 'tender_engine_profile_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    /**
     * Still waiting, and past the limit. Decided at read time — the batch may
     * yet finish, and "check again" must be able to find that out.
     */
    public function isStuck(): bool
    {
        return ! $this->isFinal()
            && $this->submitted_at->lte(now()->subSeconds((int) config('services.decision_engine.stuck_after_seconds')));
    }
}

<?php

namespace App\Enums;

/**
 * A batch's state as the engine reports it.
 *
 * `completed` and `partial` are final: the engine never changes a finalised
 * batch (DECISIONS §13.2). `partial` means every bid ran but at least one has
 * no complete result — the ranking is then provisional and carries no award
 * recommendation. "Stuck" is not a status: it is a queued or processing batch
 * that has gone past the wait limit, decided when the screen is drawn.
 */
enum EngineEvaluationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Partial = 'partial';

    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Partial;
    }

    public function labelKey(): string
    {
        return 'engine.status.'.$this->value;
    }
}

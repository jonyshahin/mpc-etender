<?php

namespace App\Enums;

/**
 * How the engine turns a bid's duration into a timeline score, relative to
 * the shortest duration in the batch (lower is better).
 *
 * The BEF forms used both, for the same criterion on the same project — most
 * likely improvisation rather than policy (INTEGRATION-DESIGN §1.4). They part
 * company on short durations, which is why the choice is shown to the officer
 * rather than inherited:
 *
 *   relative_to_best: 100 − (value − best) / best × 100, never below 0.
 *     1 day vs best 1 → 100;  2 days → 0;  4 days → 0.
 *   ratio_to_best:    best / value × 100.
 *     1 day vs best 1 → 100;  2 days → 50;  4 days → 25.
 */
enum TimelineFormula: string
{
    case RelativeToBest = 'relative_to_best';
    case RatioToBest = 'ratio_to_best';

    public function labelKey(): string
    {
        return 'engine.formula.'.$this->value;
    }

    /** One sentence on what this does to short durations. */
    public function explanationKey(): string
    {
        return 'engine.formula.'.$this->value.'_explained';
    }

    /** @return array<int, array{value: string, labelKey: string, explanationKey: string}> */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'labelKey' => $case->labelKey(),
            'explanationKey' => $case->explanationKey(),
        ], self::cases());
    }
}

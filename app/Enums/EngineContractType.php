<?php

namespace App\Enums;

/**
 * The kind of contract a tender is for, as far as engine scoring cares: it
 * picks the timeline formula.
 *
 * Only the two the BEF forms evidence are seeded (INTEGRATION-DESIGN §1.4).
 * The taxonomy is deliberately not widened: anything else is `Other`, named by
 * the officer, who must then choose the formula explicitly — so the choice is
 * made on purpose rather than copied from whichever form came to hand.
 *
 * Not to be confused with TenderType, which is the procurement method (open,
 * restricted…).
 */
enum EngineContractType: string
{
    case Installation = 'installation';
    case Supply = 'supply';
    case Other = 'other';

    /** The formula this type fixes, or null when the officer must choose. */
    public function timelineFormula(): ?TimelineFormula
    {
        return match ($this) {
            self::Installation => TimelineFormula::RelativeToBest,
            self::Supply => TimelineFormula::RatioToBest,
            self::Other => null,
        };
    }

    public function labelKey(): string
    {
        return 'engine.contract_type.'.$this->value;
    }

    /** @return array<int, array{value: string, labelKey: string, timelineFormula: ?string}> */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'labelKey' => $case->labelKey(),
            'timelineFormula' => $case->timelineFormula()?->value,
        ], self::cases());
    }
}

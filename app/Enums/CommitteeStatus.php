<?php

namespace App\Enums;

/**
 * Where an evaluation committee is in its life.
 *
 * Three sources disagreed before: the migration defaulted the column to
 * 'pending', store() wrote 'active', and update() validated
 * in:active,completed. 'pending' was reachable only by a writer that omitted
 * the column, and no picker ever offered it.
 */
enum CommitteeStatus: string
{
    case Active = 'active';
    case Completed = 'completed';

    public function labelKey(): string
    {
        return 'status.'.$this->value;
    }

    /** @return array<int, array{value: string, labelKey: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'labelKey' => $case->labelKey()],
            self::cases(),
        );
    }
}

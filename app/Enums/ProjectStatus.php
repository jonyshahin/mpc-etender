<?php

namespace App\Enums;

/**
 * Lifecycle of a construction project.
 *
 * Single source of truth for the create and edit forms, their validation, the
 * list filter and the `projects.status` cast. These were four separate literal
 * lists before, and they had already drifted: the list filter offered a
 * "draft" option that validation has never accepted, so filtering to it always
 * returned nothing.
 */
enum ProjectStatus: string
{
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Translation key for the human-facing label, shared by every picker. */
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

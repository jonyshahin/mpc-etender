<?php

namespace App\Rules;

use App\Models\SystemSetting;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * BUG-26: enforces a minimum-hours buffer between two date fields in
 * the same payload. Used for `addenda.new_opening_date` (must be at
 * least N hours after `new_deadline`); will be reused for
 * `UpdateTenderRequest` when tenders are created/edited directly.
 *
 * Buffer hours come from the SystemSetting key passed to the
 * constructor — `tender.min_hours_between_deadline_and_opening`
 * (default 24h, seeded by SystemSettingSeeder). Reading at validate
 * time (not constructor time) means setting changes apply immediately
 * without restarting the worker.
 *
 * If the other field is missing, blank, or unparseable, the rule
 * passes — required/required_if/date rules are responsible for
 * catching those upstream. This rule's only job is buffer enforcement.
 */
final class MinHoursAfter implements DataAwareRule, ValidationRule
{
    private array $data = [];

    public function __construct(
        private readonly string $otherField,
        private readonly string $settingKey = 'tender.min_hours_between_deadline_and_opening',
    ) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $other = $this->data[$this->otherField] ?? null;

        // Don't double-report: leave required/date validation to other rules.
        if (empty($value) || empty($other)) {
            return;
        }

        try {
            $thisDate = Carbon::parse($value);
            $otherDate = Carbon::parse($other);
        } catch (\Throwable) {
            return;
        }

        $bufferHours = (int) (SystemSetting::where('key', $this->settingKey)->value('value') ?? 24);

        $minThis = $otherDate->copy()->addHours($bufferHours);
        if ($thisDate->lt($minThis)) {
            $fail(__('validation.custom.min_hours_after', [
                'attribute' => $attribute,
                'other' => $this->otherField,
                'hours' => $bufferHours,
            ]));
        }
    }
}

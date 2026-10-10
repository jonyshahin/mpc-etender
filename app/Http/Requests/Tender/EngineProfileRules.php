<?php

namespace App\Http\Requests\Tender;

use App\Enums\EngineContractType;
use App\Enums\TimelineFormula;
use App\Models\EvaluationCriteriaTemplate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation for a tender's engine scoring criteria, shared by the create
 * wizard (under `engine_profile.`) and the draft editor (at the top level).
 */
trait EngineProfileRules
{
    /** @return array<string, array<int, mixed>> */
    protected function engineProfileRules(string $prefix = ''): array
    {
        $rules = [];

        foreach (EvaluationCriteriaTemplate::WEIGHTS as $column) {
            $rules[$prefix.$column] = ['required', 'integer', 'min:0', 'max:100'];
        }

        return $rules + [
            $prefix.'gate_bank_guarantee' => ['required', 'boolean'],
            $prefix.'gate_mandatory_documents' => ['required', 'boolean'],
            $prefix.'margin_below' => ['required', 'numeric', 'min:0', 'max:100'],
            $prefix.'contract_type' => ['nullable', Rule::enum(EngineContractType::class)],
            $prefix.'contract_type_label' => [
                Rule::requiredIf(fn () => $this->input($prefix.'contract_type') === EngineContractType::Other->value),
                'nullable', 'string', 'max:100',
            ],
            // Required only where the contract type does not fix it. For the
            // two seeded types whatever is sent is replaced by the type's own
            // formula (TenderEngineProfile::saving), so it cannot disagree.
            $prefix.'timeline_formula' => [
                Rule::requiredIf(fn () => $this->input($prefix.'contract_type') === EngineContractType::Other->value),
                'nullable', Rule::enum(TimelineFormula::class),
            ],
        ];
    }

    /** Weights must account for the whole score. */
    protected function engineProfileWeightsSumTo100(Validator $validator, string $prefix = ''): void
    {
        $validator->after(function (Validator $validator) use ($prefix) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $sum = 0;
            foreach (EvaluationCriteriaTemplate::WEIGHTS as $column) {
                $sum += (int) $this->input($prefix.$column);
            }

            if ($sum !== 100) {
                $validator->errors()->add($prefix.'weight_price', __('engine.publish.weights_not_100'));
            }
        });
    }
}

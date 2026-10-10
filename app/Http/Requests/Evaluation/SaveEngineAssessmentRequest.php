<?php

namespace App\Http\Requests\Evaluation;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An officer's scores (0–100) and gate attestations for one bid. Every field is
 * optional so a row can be filled in over several saves; a null gate clears an
 * attestation, which is different from attesting a failure.
 */
class SaveEngineAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('runEngineEvaluation', $this->route('tender'));
    }

    public function rules(): array
    {
        return [
            'technical_score' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'references_score' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'payment_flexibility_score' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'bank_guarantee' => ['sometimes', 'nullable', 'boolean'],
            'mandatory_documents' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}

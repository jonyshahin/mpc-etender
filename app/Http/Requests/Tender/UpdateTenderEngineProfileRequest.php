<?php

namespace App\Http\Requests\Tender;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit a draft tender's engine scoring criteria. Only while the tender is a
 * draft — TenderPolicy::update — because the criteria lock when it opens.
 */
class UpdateTenderEngineProfileRequest extends FormRequest
{
    use EngineProfileRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tender'));
    }

    public function rules(): array
    {
        return $this->engineProfileRules();
    }

    public function withValidator($validator): void
    {
        $this->engineProfileWeightsSumTo100($validator);
    }
}

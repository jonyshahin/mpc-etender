<?php

namespace App\Http\Requests\Evaluation;

use Illuminate\Foundation\Http\FormRequest;

class StoreScoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('evaluations.score');
    }

    public function rules(): array
    {
        return [
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.criterion_id' => ['required', 'uuid', 'exists:evaluation_criteria,id'],
            'scores.*.score' => ['required', 'numeric', 'min:0'],
            'scores.*.justification' => ['nullable', 'string', 'max:1000'],
            'complete' => ['boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Tender;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('tenders.create');
    }

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'envelope' => ['required', 'in:single,technical,financial'],
            'weight_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_score' => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

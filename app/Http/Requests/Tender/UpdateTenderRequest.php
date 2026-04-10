<?php

namespace App\Http\Requests\Tender;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('tenders.update');
    }

    public function rules(): array
    {
        return [
            'title_en' => ['required', 'string', 'max:500'],
            'title_ar' => ['nullable', 'string', 'max:500'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'tender_type' => ['required', 'in:open,restricted,direct_invitation,framework'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'submission_deadline' => ['required', 'date', 'after:now'],
            'opening_date' => ['required', 'date', 'after:submission_deadline'],
            'is_two_envelope' => ['required', 'boolean'],
            'technical_pass_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'requires_site_visit' => ['boolean'],
            'site_visit_date' => ['nullable', 'date', 'before:submission_deadline'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['uuid', 'exists:categories,id'],
        ];
    }
}

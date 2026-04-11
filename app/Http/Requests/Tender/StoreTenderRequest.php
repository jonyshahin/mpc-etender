<?php

namespace App\Http\Requests\Tender;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('tenders.create');
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'uuid', 'exists:projects,id'],
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

            'boq_sections' => ['nullable', 'array'],
            'boq_sections.*.title_en' => ['required_with:boq_sections', 'string', 'max:255'],
            'boq_sections.*.title_ar' => ['nullable', 'string', 'max:255'],
            'boq_sections.*.sort_order' => ['nullable', 'integer'],
            'boq_sections.*.items' => ['nullable', 'array'],
            'boq_sections.*.items.*.item_code' => ['required_with:boq_sections.*.items', 'string', 'max:50'],
            'boq_sections.*.items.*.description_en' => ['required_with:boq_sections.*.items', 'string', 'max:1000'],
            'boq_sections.*.items.*.unit' => ['required_with:boq_sections.*.items', 'string', 'max:20'],
            'boq_sections.*.items.*.quantity' => ['required_with:boq_sections.*.items', 'numeric', 'min:0'],
            'boq_sections.*.items.*.sort_order' => ['nullable', 'integer'],

            'evaluation_criteria' => ['nullable', 'array'],
            'evaluation_criteria.*.name_en' => ['required_with:evaluation_criteria', 'string', 'max:255'],
            'evaluation_criteria.*.envelope' => ['required_with:evaluation_criteria', 'in:technical,financial'],
            'evaluation_criteria.*.weight_percentage' => ['required_with:evaluation_criteria', 'numeric', 'min:0', 'max:100'],
            'evaluation_criteria.*.max_score' => ['required_with:evaluation_criteria', 'numeric', 'min:0'],
            'evaluation_criteria.*.sort_order' => ['nullable', 'integer'],
        ];
    }
}

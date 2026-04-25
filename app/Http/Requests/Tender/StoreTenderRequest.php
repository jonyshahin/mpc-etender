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

            'documents' => ['nullable', 'array'],
            'documents.*.file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xlsx,jpg,jpeg,png,zip'],
            'documents.*.title' => ['required', 'string', 'max:255'],
            'documents.*.doc_type' => ['required', 'in:specification,drawing,contract_terms,boq_template,site_photo,other'],

            'publish' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('publish')) {
            $this->merge([
                'publish' => filter_var($this->input('publish'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * User-readable labels for every dotted-path field with a server rule —
     * substituted into validation.required (and other) message templates so
     * the error reads "The Description field is required." instead of leaking
     * "The boq_sections.0.items.0.description_en field is required..." to
     * the wizard's FieldError display (BUG-14). __() ensures labels translate
     * with the current locale.
     */
    public function attributes(): array
    {
        return [
            'project_id' => __('form.project'),
            'title_en' => __('form.title_en'),
            'title_ar' => __('form.title_ar'),
            'description_en' => __('form.description_en'),
            'description_ar' => __('form.description_ar'),
            'tender_type' => __('form.tender_type'),
            'estimated_value' => __('form.estimated_value'),
            'currency' => __('form.currency'),
            'submission_deadline' => __('form.submission_deadline'),
            'opening_date' => __('form.opening_date'),
            'is_two_envelope' => __('form.two_envelope_system'),
            'technical_pass_score' => __('form.technical_pass_score'),
            'category_ids' => __('form.categories'),
            'category_ids.*' => __('form.category'),

            'boq_sections' => __('form.boq_sections'),
            'boq_sections.*.title_en' => __('form.section_title'),
            'boq_sections.*.title_ar' => __('form.section_title_ar'),
            'boq_sections.*.items' => __('form.items'),
            'boq_sections.*.items.*.item_code' => __('form.item_code'),
            'boq_sections.*.items.*.description_en' => __('form.description'),
            'boq_sections.*.items.*.unit' => __('form.unit'),
            'boq_sections.*.items.*.quantity' => __('form.quantity'),

            'evaluation_criteria' => __('form.evaluation_criteria'),
            'evaluation_criteria.*.name_en' => __('form.criterion_name'),
            'evaluation_criteria.*.envelope' => __('form.envelope'),
            'evaluation_criteria.*.weight_percentage' => __('form.weight_pct'),
            'evaluation_criteria.*.max_score' => __('form.max_score'),

            'documents' => __('form.documents'),
            'documents.*.file' => __('form.file'),
            'documents.*.title' => __('form.document_title'),
            'documents.*.doc_type' => __('form.type'),
        ];
    }

    /**
     * Drop the noisy "when X is present" suffix from required_with on nested
     * array fields (BUG-14). A user filling in BOQ items already knows the
     * items array is present — repeating that adds noise without information.
     * Each override redirects to the standard validation.required template,
     * which substitutes :attribute from the attributes() method above and is
     * already translated for both EN and AR.
     */
    public function messages(): array
    {
        return [
            'boq_sections.*.title_en.required_with' => __('validation.required'),
            'boq_sections.*.items.*.item_code.required_with' => __('validation.required'),
            'boq_sections.*.items.*.description_en.required_with' => __('validation.required'),
            'boq_sections.*.items.*.unit.required_with' => __('validation.required'),
            'boq_sections.*.items.*.quantity.required_with' => __('validation.required'),
            'evaluation_criteria.*.name_en.required_with' => __('validation.required'),
            'evaluation_criteria.*.envelope.required_with' => __('validation.required'),
            'evaluation_criteria.*.weight_percentage.required_with' => __('validation.required'),
            'evaluation_criteria.*.max_score.required_with' => __('validation.required'),
        ];
    }
}

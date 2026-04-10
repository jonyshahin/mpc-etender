<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BidDocumentUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,png,xlsx'],
            'title' => ['required', 'string', 'max:255'],
            'doc_type' => [
                'required',
                Rule::in([
                    'technical_proposal',
                    'method_statement',
                    'certificate',
                    'financial_schedule',
                    'other',
                ]),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => __('File size must not exceed 10MB.'),
            'file.mimes' => __('File must be a PDF, DOC, DOCX, JPG, PNG, or XLSX.'),
            'doc_type.in' => __('Invalid document type selected.'),
        ];
    }
}

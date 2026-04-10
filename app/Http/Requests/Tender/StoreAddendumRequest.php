<?php

namespace App\Http\Requests\Tender;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddendumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('tenders.issue_addenda');
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:500'],
            'content_en' => ['required', 'string', 'max:10000'],
            'content_ar' => ['nullable', 'string', 'max:10000'],
            'extends_deadline' => ['required', 'boolean'],
            'new_deadline' => ['nullable', 'required_if:extends_deadline,true', 'date', 'after:now'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx'],
        ];
    }
}

<?php

namespace App\Http\Requests\Tender;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenderDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('tenders.update');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xlsx,jpg,jpeg,png,zip'],
            'title' => ['required', 'string', 'max:255'],
            'doc_type' => ['required', 'in:specification,drawing,contract_terms,boq_template,site_photo,other'],
        ];
    }
}

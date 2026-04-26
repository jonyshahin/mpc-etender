<?php

namespace App\Http\Requests\Vendor;

use App\Rules\PdfFile;
use Illuminate\Foundation\Http\FormRequest;

class FileUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', new PdfFile],
            'document_type' => ['required', 'string', 'in:trade_license,insurance,financial_statement,reference,certificate,other'],
            'title' => ['required', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:issue_date'],
        ];
    }
}

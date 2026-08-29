<?php

namespace App\Http\Requests\Admin;

use App\Enums\DocumentType;
use App\Rules\PdfFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An MPC user filing a document a vendor handed over.
 *
 * Same shape and same PDF policy as the vendor's own upload
 * (Vendor\FileUploadRequest) — a document must not be distinguishable later by
 * how strictly it was validated.
 */
class VendorDocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('vendors.review_docs');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', new PdfFile],
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'title' => ['required', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:issue_date'],
        ];
    }
}

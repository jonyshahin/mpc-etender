<?php

namespace App\Http\Requests\Vendor;

use App\Enums\BidDocType;
use App\Enums\EnvelopeType;
use App\Rules\PdfFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BidDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Policy enforcement happens in the controller via Gate::forUser($vendor).
        return true;
    }

    public function rules(): array
    {
        return [
            // POLICY-01 via the shared rule (BUG-18 mandated PDF-only for bid
            // documents). Restating the cap here is what let it drift.
            'file' => ['required', new PdfFile],
            'title' => ['required', 'string', 'max:255'],
            'envelope_type' => ['required', Rule::in([
                EnvelopeType::Single->value,
                EnvelopeType::Technical->value,
                EnvelopeType::Financial->value,
            ])],
            'doc_type' => ['required', Rule::in(array_map(fn ($c) => $c->value, BidDocType::cases()))],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => __('form.file'),
            'title' => __('form.document_title'),
            'envelope_type' => __('form.envelope'),
            'doc_type' => __('form.type'),
        ];
    }
}

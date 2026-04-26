<?php

namespace App\Http\Requests\Vendor;

use App\Enums\BidDocType;
use App\Enums\EnvelopeType;
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
            // PDF only, 5 MB hard cap (5120 KB) — mandated by procurement spec
            // for bid documents (BUG-18). Max applies even for technical
            // proposals; if a vendor needs to ship a larger file they should
            // split or compress it.
            'file' => ['required', 'file', 'mimes:pdf', 'max:5120'],
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

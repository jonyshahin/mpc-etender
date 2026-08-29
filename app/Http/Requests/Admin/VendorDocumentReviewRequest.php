<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Approving or rejecting a document a vendor uploaded.
 *
 * `reason` is required only on rejection — the vendor sees it on their
 * documents page, and "rejected" with no explanation gives them nothing to act
 * on. The controller applies that rule per route rather than here, so both
 * decisions can share one request class.
 */
class VendorDocumentReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('vendors.review_docs');
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

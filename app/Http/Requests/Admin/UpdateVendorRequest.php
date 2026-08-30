<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an admin correcting a vendor's details.
 *
 * Mirrors StoreVendorRequest field for field so a record cannot be created in
 * a shape that editing then rejects. Two differences:
 *
 * - `email` ignores this vendor's own row, or saving an unchanged form would
 *   fail against its own record.
 * - No `password`. Credentials are handled by the reissue/reset actions, which
 *   carry their own audit trail; folding them in here would let a routine
 *   detail edit silently lock a vendor out.
 */
class UpdateVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('vendor')) ?? false;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'company_name_ar' => ['nullable', 'string', 'max:255'],
            'trade_license_no' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],

            'contact_person' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('vendors', 'email')->ignore($this->route('vendor')->id),
            ],
            'phone' => ['required', 'string', 'max:20'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],

            // `distinct`: vendor_categories carries unique(vendor_id, category_id),
            // so a repeated id would fail the sync and roll the edit back.
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['uuid', 'distinct', 'exists:categories,id'],

            // Drives the locale of notifications sent to this vendor. Not
            // `nullable`: vendors.language_pref is NOT NULL, so a null would be a
            // 500 on MySQL while SQLite quietly accepted it.
            'language_pref' => ['sometimes', 'required', 'in:en,ar,ku'],
        ];
    }
}

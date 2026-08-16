<?php

namespace App\Http\Requests\Admin;

use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates admin-initiated vendor onboarding.
 *
 * Replaces the old public Vendor\VendorRegistrationRequest: vendor
 * self-registration was removed, so the only way a vendor record is created is
 * an admin submitting this form from /admin/vendors.
 *
 * Deliberately has no `password` field — the admin never chooses the vendor's
 * password. Admin\VendorController::store generates a temporary one and forces
 * a change on first login.
 */
class StoreVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Vendor::class) ?? false;
    }

    public function rules(): array
    {
        return [
            // Company info
            'company_name' => ['required', 'string', 'max:255'],
            'company_name_ar' => ['nullable', 'string', 'max:255'],
            'trade_license_no' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],

            // Contact person
            'contact_person' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:vendors,email'],
            'phone' => ['required', 'string', 'max:20'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],

            // Categories. `distinct` matters: vendor_categories carries a
            // unique(vendor_id, category_id) index, so a repeated id would blow
            // up the insert and roll back the whole onboarding.
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['uuid', 'distinct', 'exists:categories,id'],

            // Language — drives the locale of notifications sent to this vendor.
            // Not `nullable`: vendors.language_pref is NOT NULL DEFAULT 'ar', so a
            // null would be a 500 on MySQL (SQLite would quietly accept it).
            // Omitting the field is fine — the column default applies.
            'language_pref' => ['sometimes', 'required', 'in:en,ar,ku'],
        ];
    }
}

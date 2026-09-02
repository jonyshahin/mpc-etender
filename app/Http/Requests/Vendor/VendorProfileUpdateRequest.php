<?php

namespace App\Http\Requests\Vendor;

use App\Http\Controllers\Vendor\ProfileController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Vendor guard handles auth
    }

    public function rules(): array
    {
        $vendorId = $this->user('vendor')->id;

        return [
            'company_name' => ['required', 'string', 'max:255'],
            'company_name_ar' => ['nullable', 'string', 'max:255'],
            'trade_license_no' => ['required', 'string', 'max:50'],
            'contact_person' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('vendors')->ignore($vendorId)],
            'phone' => ['required', 'string', 'max:20'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            // From the same constant the picker is built from, so the two
            // cannot drift. Includes 'ku': admins can onboard a vendor as
            // Kurdish, so the vendor must be able to keep that preference when
            // editing their own profile. Not `nullable` — the column is NOT
            // NULL DEFAULT 'ar'.
            'language_pref' => ['sometimes', 'required', Rule::in(ProfileController::LANGUAGES)],
        ];
    }
}

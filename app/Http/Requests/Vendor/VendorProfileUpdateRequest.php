<?php

namespace App\Http\Requests\Vendor;

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
            'language_pref' => ['nullable', 'in:en,ar'],
        ];
    }
}

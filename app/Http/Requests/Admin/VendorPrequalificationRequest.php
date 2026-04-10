<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class VendorPrequalificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('vendors.qualify');
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

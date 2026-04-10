<?php

namespace App\Http\Requests\Tender;

use Illuminate\Foundation\Http\FormRequest;

class StoreClarificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Vendors can ask clarifications
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:5000'],
        ];
    }
}

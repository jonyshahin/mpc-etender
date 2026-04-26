<?php

namespace App\Http\Requests\Vendor;

use App\Rules\PdfFile;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('vendor') !== null;
    }

    public function rules(): array
    {
        return [
            'justification' => ['required', 'string', 'min:20', 'max:2000'],
            'add_categories' => ['array'],
            'add_categories.*' => ['uuid', 'exists:categories,id'],
            'remove_categories' => ['array'],
            'remove_categories.*' => ['uuid', 'exists:categories,id'],
            'evidence' => ['required', 'array', 'min:1', 'max:10'],
            'evidence.*' => [new PdfFile],
        ];
    }
}

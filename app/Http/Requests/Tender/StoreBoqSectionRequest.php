<?php

namespace App\Http\Requests\Tender;

use Illuminate\Foundation\Http\FormRequest;

class StoreBoqSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('tenders.manage_boq');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

<?php

namespace App\Http\Requests\Tender;

use Illuminate\Foundation\Http\FormRequest;

class StoreBoqItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('tenders.manage_boq');
    }

    public function rules(): array
    {
        return [
            'item_code' => ['required', 'string', 'max:50'],
            'description_en' => ['required', 'string', 'max:1000'],
            'description_ar' => ['nullable', 'string', 'max:1000'],
            'unit' => ['required', 'string', 'max:20'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

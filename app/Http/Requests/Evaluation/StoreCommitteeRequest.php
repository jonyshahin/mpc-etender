<?php

namespace App\Http\Requests\Evaluation;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommitteeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('evaluations.manage_committees');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'committee_type' => ['required', 'in:technical,financial,combined'],
        ];
    }
}

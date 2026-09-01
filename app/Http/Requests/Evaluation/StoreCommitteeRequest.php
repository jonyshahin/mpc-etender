<?php

namespace App\Http\Requests\Evaluation;

use App\Enums\CommitteeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'committee_type' => ['required', Rule::enum(CommitteeType::class)],
        ];
    }
}

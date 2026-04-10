<?php

namespace App\Http\Requests\Evaluation;

use Illuminate\Foundation\Http\FormRequest;

class OpenBidsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('bids.open');
    }

    public function rules(): array
    {
        return [
            'authorizer_id' => ['required', 'uuid', 'exists:users,id', 'different:opener_id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['opener_id' => $this->user()->id]);
    }
}

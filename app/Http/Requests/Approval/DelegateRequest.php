<?php

namespace App\Http\Requests\Approval;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DelegateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user->can('approvals.level1')
            || $user->can('approvals.level2')
            || $user->can('approvals.level3');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'delegatee_id' => ['required', 'uuid', 'exists:users,id'],
            'comments' => ['nullable', 'string'],
        ];
    }
}

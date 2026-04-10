<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('admin.users');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'password' => ['nullable', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
            'language_pref' => ['nullable', 'in:en,ar'],
            'is_active' => ['required', 'boolean'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['uuid', 'exists:projects,id'],
        ];
    }
}

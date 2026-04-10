<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('admin.users');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
            'language_pref' => ['nullable', 'in:en,ar'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['uuid', 'exists:projects,id'],
        ];
    }
}

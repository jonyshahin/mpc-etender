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
            // 'ku' included: SetLocale accepts en/ar/ku and lang/ku.json ships.
            // Not `nullable` — the column is NOT NULL DEFAULT 'en', so an explicit
            // null would 500 on MySQL while passing on SQLite. Omitting the field
            // is still fine; the column default applies. (BUG-06)
            'language_pref' => ['sometimes', 'required', 'in:en,ar,ku'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['uuid', 'exists:projects,id'],
        ];
    }
}

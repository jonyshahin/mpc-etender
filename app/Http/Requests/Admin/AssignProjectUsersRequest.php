<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignProjectUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('admin.projects');
    }

    public function rules(): array
    {
        return [
            'users' => ['required', 'array', 'min:1'],
            'users.*.user_id' => ['required', 'uuid', 'exists:users,id'],
            'users.*.project_role' => ['required', 'string', 'max:50'],
        ];
    }
}

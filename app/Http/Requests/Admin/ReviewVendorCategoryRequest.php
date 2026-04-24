<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewVendorCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('web');

        return $user !== null && $user->hasPermission('vendors.review_category_requests');
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'comments' => [
                'nullable',
                'string',
                'max:2000',
                // Reject requires non-empty comments. Approve may omit them.
                Rule::requiredIf(fn () => $this->input('action') === 'reject'),
            ],
        ];
    }
}

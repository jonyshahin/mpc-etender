<?php

namespace App\Http\Requests\Approval;

use App\Models\ApprovalRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DelegateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Same bare-slug mistake as ApprovalDecisionRequest, with the same effect:
     * `can('approvals.level1')` is not an ability Gate knows, so delegation was
     * refused for everyone. Asked of the policy now, against this approval —
     * you cannot pass on an authority you do not hold.
     */
    public function authorize(): bool
    {
        $approval = $this->route('approval');

        return $approval instanceof ApprovalRequest
            && $this->user()->can('delegate', $approval);
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

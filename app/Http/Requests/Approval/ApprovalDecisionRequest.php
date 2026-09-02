<?php

namespace App\Http\Requests\Approval;

use App\Models\ApprovalRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApprovalDecisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Asked of the policy, against this particular approval.
     *
     * `can('approvals.level1')` — what this used to ask — passes a bare
     * permission slug as an ability name. That names neither a registered gate
     * nor a policy method, so Gate fell through to its default deny for every
     * user: approve and reject were 403 for everyone, always. The whole
     * decision flow was dead, not merely the queue that lists it.
     *
     * Even had it resolved, the old form asked only whether the caller holds
     * *some* level, never whether they hold *this request's* level. A chain
     * escalates 1 → 2 → 3 by award value, so a level-1 approver could have
     * signed the largest award in the system.
     */
    public function authorize(): bool
    {
        $approval = $this->route('approval');

        return $approval instanceof ApprovalRequest
            && $this->user()->can('approve', $approval);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'comments' => ['required', 'string', 'max:2000'],
        ];
    }
}

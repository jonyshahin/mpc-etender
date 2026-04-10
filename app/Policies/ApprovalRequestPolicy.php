<?php

namespace App\Policies;

use App\Models\ApprovalRequest;
use App\Models\User;

class ApprovalRequestPolicy
{
    public function view(User $user, ApprovalRequest $request): bool
    {
        return $user->isAssignedToProject($request->tender->project_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('evaluations.finalize_reports');
    }

    public function approve(User $user, ApprovalRequest $request): bool
    {
        $level = $request->approval_level;

        return $user->hasPermission("approvals.level{$level}");
    }
}

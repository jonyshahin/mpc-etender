<?php

namespace App\Policies;

use App\Models\EvaluationReport;
use App\Models\User;

class EvaluationReportPolicy
{
    public function view(User $user, EvaluationReport $report): bool
    {
        return $user->isAssignedToProject($report->tender->project_id)
            && $user->hasPermission('evaluations.view');
    }

    public function generate(User $user): bool
    {
        return $user->hasPermission('evaluations.generate_reports');
    }

    public function finalize(User $user, EvaluationReport $report): bool
    {
        return $user->isAssignedToProject($report->tender->project_id)
            && $user->hasPermission('evaluations.finalize_reports');
    }
}

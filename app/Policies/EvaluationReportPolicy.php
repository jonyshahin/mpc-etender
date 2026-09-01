<?php

namespace App\Policies;

use App\Models\EvaluationReport;
use App\Models\Tender;
use App\Models\User;

class EvaluationReportPolicy
{
    /**
     * Reading the evaluation outcome for a tender, report row or not.
     *
     * show() computes a live ranking when no report has been saved yet, so
     * gating only on an EvaluationReport instance would leave that path open.
     */
    public function viewAny(User $user, Tender $tender): bool
    {
        return $user->isAssignedToProject($tender->project_id)
            && $user->hasPermission('evaluations.view');
    }

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

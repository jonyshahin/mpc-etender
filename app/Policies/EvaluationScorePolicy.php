<?php

namespace App\Policies;

use App\Models\EvaluationScore;
use App\Models\Tender;
use App\Models\User;

class EvaluationScorePolicy
{
    /**
     * User must be a member of the tender's evaluation committee.
     */
    public function score(User $user, Tender $tender): bool
    {
        return $user->hasPermission('evaluations.score')
            && $tender->committees()
                ->whereHas('committeeMemberRecords', fn ($q) => $q->where('user_id', $user->id))
                ->exists();
    }

    public function view(User $user, EvaluationScore $score): bool
    {
        return $user->isAssignedToProject($score->bid->tender->project_id);
    }
}

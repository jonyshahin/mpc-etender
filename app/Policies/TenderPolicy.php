<?php

namespace App\Policies;

use App\Enums\TenderStatus;
use App\Models\Tender;
use App\Models\User;

class TenderPolicy
{
    public function view(User $user, Tender $tender): bool
    {
        return $user->isAssignedToProject($tender->project_id);
    }

    /**
     * Forming and staffing evaluation committees.
     *
     * Not update(): that additionally demands the tender still be Draft, and
     * committees are formed once bidding is over. The permission alone was
     * not enough either — it is global, so a holder could staff committees on
     * projects they have nothing to do with.
     */
    public function manageCommittees(User $user, Tender $tender): bool
    {
        return $user->hasPermission('evaluations.manage_committees')
            && $user->isAssignedToProject($tender->project_id);
    }

    /**
     * See the engine's scoring of this tender. Same audience as the existing
     * evaluation screens: anyone on the project who can view evaluations.
     */
    public function viewEngineEvaluation(User $user, Tender $tender): bool
    {
        return $user->hasPermission('evaluations.view')
            && $user->isAssignedToProject($tender->project_id);
    }

    /**
     * Enter scores and gate attestations, and run the engine. A separate
     * permission because attesting a bank guarantee is a signed statement,
     * not something every evaluation viewer should be able to make.
     */
    public function runEngineEvaluation(User $user, Tender $tender): bool
    {
        return $user->hasPermission('evaluations.engine_run')
            && $user->isAssignedToProject($tender->project_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tenders.create');
    }

    public function update(User $user, Tender $tender): bool
    {
        return $tender->status === TenderStatus::Draft
            && $user->isAssignedToProject($tender->project_id)
            && $user->hasPermission('tenders.update');
    }

    public function publish(User $user, Tender $tender): bool
    {
        return $tender->status === TenderStatus::Draft
            && $user->isAssignedToProject($tender->project_id)
            && $user->hasPermission('tenders.publish');
    }

    public function cancel(User $user, Tender $tender): bool
    {
        return $user->isAssignedToProject($tender->project_id)
            && $user->hasPermission('tenders.cancel');
    }

    public function delete(User $user, Tender $tender): bool
    {
        return $tender->status === TenderStatus::Draft
            && $user->isAssignedToProject($tender->project_id)
            && $user->hasPermission('tenders.delete');
    }
}

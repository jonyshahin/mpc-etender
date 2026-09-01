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

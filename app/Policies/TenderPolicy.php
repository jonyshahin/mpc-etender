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

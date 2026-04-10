<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $user->isAssignedToProject($project->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('admin.projects');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->isAssignedToProject($project->id)
            && $user->hasPermission('admin.projects');
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermission('admin.projects');
    }
}

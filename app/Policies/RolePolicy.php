<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Who may change what a role can do.
 *
 * RoleController::update() already refused to rename an `is_system` role, but
 * updatePermissions() had no guard at all — so the protection was walked around
 * by editing what a role *can do* rather than what it is *called*.
 */
class RolePolicy
{
    /**
     * Rewrite a role's permission set.
     *
     * Deliberately not "refuse every is_system role": every seeded role
     * (super_admin, admin, procurement_officer, project_manager, evaluator) is
     * a system role, so that would leave the permissions screen able to
     * configure nothing real. The escalation lives in two specific places
     * instead, and both are closed here.
     */
    public function updatePermissions(User $actor, Role $role): bool
    {
        if (! $actor->hasPermission('admin.roles')) {
            return false;
        }

        // Granting your own role something it lacked is self-escalation by the
        // other door — the account keeps its role name and quietly gains reach.
        if ($role->id === $actor->role_id) {
            return false;
        }

        // The crown jewels. Only a super admin may redefine super admin.
        return ! $role->isSuperAdmin() || $actor->isSuperAdmin();
    }
}

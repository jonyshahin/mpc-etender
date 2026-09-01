<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Who may administer whom.
 *
 * There was no policy here at all. `admin.users` was a single flat permission
 * and nothing looked at *which* role was being assigned or *whose* account was
 * being edited, so anyone holding the admin role could hand themselves or
 * anyone else super admin in one request, demote every super admin, or
 * deactivate all of them.
 */
class UserPolicy
{
    /**
     * Edit someone's account.
     *
     * A super admin is only administrable by another super admin — otherwise
     * the lower role can neutralise the higher one.
     */
    public function update(User $actor, User $target): bool
    {
        if (! $actor->hasPermission('admin.users')) {
            return false;
        }

        return ! $target->isSuperAdmin() || $actor->isSuperAdmin();
    }

    /**
     * Put a specific role on an account.
     *
     * Checked separately from update() because the danger is in the payload,
     * not the target: assigning super admin is the escalation, whoever receives
     * it.
     */
    public function assignRole(User $actor, Role $role): bool
    {
        return ! $role->isSuperAdmin() || $actor->isSuperAdmin();
    }

    /**
     * Change your own role — never.
     *
     * Self-promotion is the shortest path to super admin and has no legitimate
     * use: an account's own level should be set by someone else. Rule 5 keeps a
     * super admin in existence to do it.
     */
    public function changeOwnRole(User $actor, User $target): bool
    {
        return $actor->id !== $target->id;
    }

    /** Deactivating an account. Self-deactivation stays blocked as before. */
    public function deactivate(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        return $this->update($actor, $target);
    }
}

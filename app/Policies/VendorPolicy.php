<?php

namespace App\Policies;

use App\Models\User;

class VendorPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('vendors.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vendors.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('vendors.update');
    }

    public function qualify(User $user): bool
    {
        return $user->hasPermission('vendors.qualify');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('vendors.delete');
    }

    public function reviewDocuments(User $user): bool
    {
        return $user->hasPermission('vendors.review_docs');
    }
}

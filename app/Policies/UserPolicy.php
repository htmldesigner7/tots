<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function update(User $user, User $target): bool
    {
        // only a Super Administrator may edit another Super Administrator
        return $user->hasPermission('users.manage') && (! $target->isSuperAdmin() || $user->isSuperAdmin());
    }

    public function delete(User $user, User $target): bool
    {
        if ($user->is($target) || ! $this->update($user, $target)) {
            return false;
        }
        if ($target->isSuperAdmin()) {
            return User::whereHas('role', fn ($q) => $q->where('slug', 'super-admin'))->where('is_active', true)->count() > 1;
        }

        return true;
    }

    /** Whether $user may give $role to someone. */
    public function assignRole(User $user, Role $role): bool
    {
        return $role->slug !== 'super-admin' || $user->isSuperAdmin();
    }
}

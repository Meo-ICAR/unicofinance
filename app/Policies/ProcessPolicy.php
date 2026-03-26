<?php

namespace App\Policies;

use App\Models\Process;
use App\Models\User;

class ProcessPolicy
{
    /**
     * Super Admin bypass.
     */
    public function before(User $user, $ability): ?bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Solo Super Admin e Admin dell'azienda possono vedere i processi
        return $user->is_super_admin || $user->isTenantAdmin() || $user->isTenantInspector();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Process $process): bool
    {
        return $user->is_super_admin || $user->isTenantAdmin() || $user->isTenantInspector();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Process $process): bool
    {
        return $user->is_super_admin || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Process $process): bool
    {
        return $user->is_super_admin || $user->isTenantAdmin();
    }
}

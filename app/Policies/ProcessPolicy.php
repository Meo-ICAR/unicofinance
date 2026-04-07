<?php

namespace App\Policies;

use App\Models\Process;
use Illuminate\Auth\Access\Response;

class ProcessPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny($user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view($user, Process $process): bool
    {
        return $user->companies()->where('company_id', $process->company_id)->exists() || $user->is_super_admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create($user): bool
    {
        return $user->companies()->exists() || $user->is_super_admin;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update($user, Process $process): bool
    {
        return $user->companies()->where('company_id', $process->company_id)->exists() || $user->is_super_admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete($user, Process $process): bool
    {
        return $user->companies()->where('company_id', $process->company_id)->exists() || $user->is_super_admin;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore($user, Process $process): bool
    {
        return $user->companies()->where('company_id', $process->company_id)->exists() || $user->is_super_admin;
    }

    /**
     * Determine whether the user can force delete the model.
     */
    public function forceDelete($user, Process $process): bool
    {
        return $user->is_super_admin;
    }
}

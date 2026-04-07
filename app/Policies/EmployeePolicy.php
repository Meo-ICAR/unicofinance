<?php

namespace App\Policies;

use App\Models\Employee;
use Illuminate\Auth\Access\Response;

class EmployeePolicy
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
    public function view($user, Employee $employee): bool
    {
        return $user->companies()->where('company_id', $employee->company_id)->exists() || $user->is_super_admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create($user): bool
    {
        return true; // Allow all authenticated users to create employees
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update($user, Employee $employee): bool
    {
        return $user->companies()->where('company_id', $employee->company_id)->exists() || $user->is_super_admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete($user, Employee $employee): bool
    {
        return true; // Allow all authenticated users to delete employees
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore($user, Employee $employee): bool
    {
        return true; // Allow all authenticated users to restore employees
    }

    /**
     * Determine whether the user can force delete the model.
     */
    public function forceDelete($user, Employee $employee): bool
    {
        return $user->is_super_admin;
    }
}

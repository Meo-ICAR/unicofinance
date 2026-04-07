<?php

namespace App\Policies;

use App\Models\Client;
use Illuminate\Auth\Access\Response;

class ClientPolicy
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
    public function view($user, Client $client): bool
    {
        return $user->companies()->where('company_id', $client->company_id)->exists() || $user->is_super_admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create($user): bool
    {
        return true; // Allow all authenticated users to create clients
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update($user, Client $client): bool
    {
        return $user->companies()->where('company_id', $client->company_id)->exists() || $user->is_super_admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete($user, Client $client): bool
    {
        return true; // Allow all authenticated users to delete clients
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore($user, Client $client): bool
    {
        return true; // Allow all authenticated users to restore clients
    }

    /**
     * Determine whether the user can force delete the model.
     */
    public function forceDelete($user, Client $client): bool
    {
        return $user->is_super_admin;
    }
}

<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    // Chi può vedere la voce "Users" nel menu?
    public function viewAny(User $user): bool
    {
        // Super Admin, Admin e Inspector possono vedere la lista
        return $user->is_super_admin || $user->isTenantAdmin() || $user->isTenantInspector();
    }

    // Chi può vedere il singolo utente?
    public function view(User $user, User $model): bool
    {
        return $user->is_super_admin || $user->isTenantAdmin() || $user->isTenantInspector();
    }

    // Chi può creare nuovi utenti? (L'Inspector e lo User normale vengono bloccati qui)
    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->isTenantAdmin();
    }

    // Chi può modificare gli utenti?
    public function update(User $user, User $model): bool
    {
        return $user->is_super_admin || $user->isTenantAdmin();
    }

    // Chi può eliminare gli utenti?
    public function delete(User $user, User $model): bool
    {
        return $user->is_super_admin || $user->isTenantAdmin();
    }
}

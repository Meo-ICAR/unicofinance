<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Unico\Core\Models\User as CoreUser;

class User extends CoreUser implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'agenti') {
            return $this->fornitore()->exists();
        }

        return true;
    }
}

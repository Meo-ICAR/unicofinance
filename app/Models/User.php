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

    /**
     * Profili interni che usano il pannello admin.
     *
     * @var list<string>
     */
    public const STAFF_ROLES = ['admin', 'super_admin', 'employee', 'inspector', 'user'];

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'agenti') {
            return $this->fornitore()->exists();
        }

        return $this->isStaff();
    }

    public function isStaff(): bool
    {
        return in_array($this->role, self::STAFF_ROLES, true);
    }
}

<?php

namespace App\Models;

use App\Services\AvatarSocialite;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Unico\Core\Models\User as CoreUser;

class User extends CoreUser implements FilamentUser, HasAvatar
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

    /** La foto dell'account Microsoft o Google, nel menu dell'utente di Filament. */
    public function getFilamentAvatarUrl(): ?string
    {
        return app(AvatarSocialite::class)->url($this->getKey());
    }

    public function isStaff(): bool
    {
        return in_array($this->role, self::STAFF_ROLES, true);
    }
}

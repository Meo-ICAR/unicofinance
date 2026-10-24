<?php

namespace App\Models;

use App\Services\Agenti\DipendenteDelloAgente;
use App\Services\AvatarSocialite;
use App\Support\Istruttori;
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
            return $this->fornitore()->exists() && ! $this->isProduttoreIstruttore();
        }

        return $this->isStaff() || $this->isProduttoreIstruttore();
    }

    /** Un produttore che è anche istruttore (dipendente con quel ruolo) lavora solo nel pannello admin, non nel portale agenti. */
    public function isProduttoreIstruttore(): bool
    {
        $produttore = $this->fornitore;

        if ($produttore === null) {
            return false;
        }

        $dipendente = app(DipendenteDelloAgente::class)->trova($produttore);

        return $dipendente !== null && Istruttori::e($dipendente);
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

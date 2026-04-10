<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Traits\HasBpmNavigation;

#[Fillable(['name', 'email', 'password', 'is_approved'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasBpmNavigation;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',  // <-- AGGIUNGI QUESTA RIGA
        ];
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->avatar_url) {
            return $this->avatar_url;
        }

        $socialUser = $this->socialiteUsers()->whereNotNull('avatar')->first();
        if ($socialUser) {
            return $socialUser->avatar;
        }

        return null;
    }

    public function socialiteUsers(): HasMany
    {
        return $this->hasMany(SocialiteUser::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_super_admin || $this->is_approved;
    }

    // Ritorna le aziende visibili nel menu a tendina dell'utente
    public function getTenants(Panel $panel): Collection
    {
        // Se è Super Admin, carica e mostra TUTTE le aziende nel sistema
        if ($this->is_super_admin) {
            return Company::all();
        }

        // Altrimenti, mostra solo le aziende a cui l'utente è collegato
        return $this->companies;
    }

    // Verifica se l'utente ha il permesso di entrare in un'azienda specifica
    public function canAccessTenant(Model $tenant): bool
    {
        // Il Super Admin può accedere sempre e ovunque
        if ($this->is_super_admin) {
            return true;
        }

        // Gli utenti normali possono accedere solo se esiste il record nella pivot
        return $this->companies()->whereKey($tenant)->exists();
    }

    // --- HELPER PER I RUOLI ---

    // Ottiene il ruolo dell'utente nell'azienda attualmente attiva in Filament
    public function getCurrentTenantRole(): ?string
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return null;
        }

        // Cerca l'utente nella pivot per questo tenant e restituisce il ruolo
        return $this->companies()->whereKey($tenant->id)->first()?->pivot->role;
    }

    public function isTenantAdmin(): bool
    {
        return $this->getCurrentTenantRole() === 'admin';
    }

    public function isTenantInspector(): bool
    {
        return $this->getCurrentTenantRole() === 'inspector';
    }



    /**
     * Relazione Eloquent: Un Utente ha un Impiegato
     */
    public function employee()
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    /**
     * Relazione Eloquent: Un Utente ha un Impiegato
     */
    public function consultant()
    {
        return $this->hasOne(Client::class, 'user_id');
    }

    public function hasBpmPermission(string $resource, string $ability): bool
    {
        $permissions = $this->getBpmPermissions();

        if (in_array('*', $permissions) || in_array("{$resource}.*", $permissions)) {
            return true;
        }

        return in_array("{$resource}.{$ability}", $permissions);
    }
}

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

#[Fillable(['name', 'email', 'password', 'is_approved'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    /**
     * Motore di estrazione basato sui Modelli Eloquent
     */
    public function getBpmPermissions(): array
    {
        $cacheKey = "user_{$this->id}_bpm_permissions";

        return Cache::remember($cacheKey, 300, function () {

            // A. Recuperiamo l'impiegato tramite la relazione.
            // Nota: se il modello Employee usa il trait SoftDeletes,
            // Laravel aggiungerà automaticamente "whereNull('deleted_at')".
            $employee = $this->employee;

            if (!$employee) {
                return [];
            }

            $permissions = [];

            // B. Recuperiamo le pratiche usando l'Eager Loading (with) per evitare il problema N+1
            $tasks = $employee->taskExecutions()->with('processTask.process')->get();

            // C. Mappiamo gli stati
            foreach ($tasks as $task) {
                // Navighiamo le relazioni Eloquent in modo pulito
                $process = $task->processTask->process;

                $resource = $process->target_model
                    ? strtolower(class_basename($process->target_model))
                    : Str::slug($process->name, '_');

                $permissions[] = "{$resource}.lettura";

                if (in_array($task->status, ['todo', 'in_progress'])) {
                    $permissions[] = "{$resource}.modifica";
                    $permissions[] = "{$resource}.creazione";
                    $permissions[] = "{$resource}.esecuzione";
                }
            }

            // D. Gestione Supervisori (Accesso Gerarchico) tramite "whereHas"
            if ($employee->supervisor_type !== 'no') {

                // Cerchiamo i Task che appartengono agli impiegati coordinati da questo utente
                $subordinateTasks = TaskExecution::with('processTask.process')
                    ->whereHas('employee', function ($query) use ($employee) {
                        $query->where('coordinated_by_id', $employee->id);
                    })
                    ->get();

                foreach ($subordinateTasks as $subTask) {
                    $process = $subTask->processTask->process;

                    $resource = $process->target_model
                        ? strtolower(class_basename($process->target_model))
                        : Str::slug($process->name, '_');

                    $permissions[] = "{$resource}.lettura";
                }
            }

            return array_values(array_unique($permissions));
        });
    }
}

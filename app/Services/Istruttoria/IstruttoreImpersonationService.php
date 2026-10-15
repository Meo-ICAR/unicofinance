<?php

namespace App\Services\Istruttoria;

use App\Filament\Resources\Istruttori\IstruttoreResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use RuntimeException;
use Unico\Core\Models\Employee;

/** Permette a un amministratore di entrare nel pannello come un istruttore (per dimostrazioni e assistenza). */
class IstruttoreImpersonationService
{
    public const SESSION_KEY = 'istruttori_impersonation';

    /** Solo amministratori; l'istruttore deve avere un utente con accesso al pannello e non essere l'utente stesso. */
    public function canImpersonate(?User $actor, Employee $dipendente): bool
    {
        if ($actor === null || ! in_array($actor->role, ['admin', 'super_admin'], true) || $this->isImpersonating()) {
            return false;
        }

        $utente = $this->utente($dipendente);

        return $utente !== null && ! $utente->is($actor);
    }

    public function isImpersonating(): bool
    {
        return filled(Session::get(self::SESSION_KEY.'.impersonator_id'));
    }

    public function utente(Employee $dipendente): ?User
    {
        $utente = $dipendente->user_id === null || $dipendente->deleted_at !== null || ! $dipendente->is_active ? null : User::query()->find($dipendente->user_id);

        return $utente instanceof User && $utente->isStaff() ? $utente : null;
    }

    /** Avvia l'impersonazione e restituisce l'indirizzo della dashboard dell'istruttore. */
    public function start(User $actor, Employee $dipendente): string
    {
        if (! $this->canImpersonate($actor, $dipendente)) {
            throw new RuntimeException('Impersonazione non consentita per questo istruttore.');
        }

        Auth::login($this->utente($dipendente));
        Session::regenerate();
        Session::put(self::SESSION_KEY, ['impersonator_id' => $actor->getKey(), 'employee_id' => $dipendente->getKey(), 'nome' => $dipendente->name]);

        return url('/admin');
    }

    /** Il nome dell'istruttore che si sta impersonando. */
    public function nome(): ?string
    {
        return Session::get(self::SESSION_KEY.'.nome');
    }

    /** Termina l'impersonazione e restituisce l'indirizzo dell'elenco istruttori. */
    public function leave(): string
    {
        $impersonatorId = Session::get(self::SESSION_KEY.'.impersonator_id');

        if ($impersonatorId === null) {
            throw new RuntimeException('Nessuna impersonazione attiva.');
        }

        $impersonator = User::query()->find($impersonatorId);

        if (! $impersonator instanceof User || ! in_array($impersonator->role, ['admin', 'super_admin'], true)) {
            Session::forget(self::SESSION_KEY);
            Auth::logout();
            Session::invalidate();
            Session::regenerateToken();

            throw new RuntimeException('Impersonatore non più valido.');
        }

        Auth::login($impersonator);
        Session::regenerate();
        Session::forget(self::SESSION_KEY);

        return IstruttoreResource::getUrl(panel: 'admin');
    }
}

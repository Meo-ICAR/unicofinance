<?php

namespace App\Services\Agenti;

use App\Filament\Resources\Produttori\ProduttoreResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use RuntimeException;
use Unico\Core\Models\Fornitore;

/** Permette allo staff di entrare nel portale agenti come un produttore attivo. */
class AgentImpersonationService
{
    public const SESSION_KEY = 'agenti_impersonation';

    public function canImpersonate(?User $actor, Fornitore $fornitore): bool
    {
        if ($actor === null || ! $actor->isStaff() || ! $fornitore->is_active) {
            return false;
        }

        return $this->resolveAgentUser($fornitore) !== null;
    }

    public function isImpersonating(): bool
    {
        return filled(Session::get(self::SESSION_KEY.'.impersonator_id'));
    }

    /** Avvia l'impersonazione e restituisce l'URL del portale agenti. */
    public function start(User $actor, Fornitore $fornitore): string
    {
        if (! $this->canImpersonate($actor, $fornitore)) {
            throw new RuntimeException('Impersonazione non consentita per questo produttore.');
        }

        $agent = $this->resolveAgentUser($fornitore);

        if ($agent === null) {
            throw new RuntimeException('Utente agente non disponibile.');
        }

        Auth::login($agent);
        Session::regenerate();
        Session::put(self::SESSION_KEY, [
            'impersonator_id' => $actor->getKey(),
            'fornitore_id' => $fornitore->getKey(),
        ]);

        return Filament::getPanel('agenti')->getUrl();
    }

    /** Termina l'impersonazione e restituisce l'URL dell'elenco produttori. */
    public function leave(): string
    {
        $context = Session::get(self::SESSION_KEY, []);
        $impersonatorId = $context['impersonator_id'] ?? null;

        if ($impersonatorId === null) {
            throw new RuntimeException('Nessuna impersonazione attiva.');
        }

        $impersonator = User::query()->find($impersonatorId);

        if (! $impersonator instanceof User || ! $impersonator->isStaff()) {
            Session::forget(self::SESSION_KEY);
            Auth::logout();
            Session::invalidate();
            Session::regenerateToken();

            throw new RuntimeException('Impersonatore non più valido.');
        }

        Auth::login($impersonator);
        Session::regenerate();
        Session::forget(self::SESSION_KEY);

        return ProduttoreResource::getUrl(panel: 'admin');
    }

    private function resolveAgentUser(Fornitore $fornitore): ?User
    {
        if ($fornitore->user_id === null) {
            return null;
        }

        $user = User::query()->find($fornitore->user_id);

        if (! $user instanceof User) {
            return null;
        }

        if (! $user->canAccessPanel(Filament::getPanel('agenti'))) {
            return null;
        }

        return $user;
    }
}

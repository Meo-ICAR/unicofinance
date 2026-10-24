<?php

namespace App\Services\Agenti;

use Unico\Core\Models\Employee;
use Unico\Core\Models\Fornitore;

/** Il dipendente che è anche il produttore: stesso utente di accesso o codice fiscale, altrimenti lo stesso nome. */
class DipendenteDelloAgente
{
    public function trova(Fornitore $agente): ?Employee
    {
        $dipendenti = Employee::query()->where('is_active', true)->orderBy('id')->get();

        return $dipendenti->first(fn (Employee $dipendente): bool => ($agente->user_id !== null && $dipendente->user_id === $agente->user_id)
                || (filled($agente->cf) && strcasecmp((string) $dipendente->cf, (string) $agente->cf) === 0))
            ?? $dipendenti->first(fn (Employee $dipendente): bool => $this->nome($dipendente->name) !== '' && $this->nome($dipendente->name) === $this->nome($agente->name));
    }

    /** Nome senza maiuscole, spazi doppi e ordine: «FUMMO BRIGIDA» e «Brigida  Fummo» coincidono. */
    private function nome(?string $nome): string
    {
        return collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string) $nome), -1, PREG_SPLIT_NO_EMPTY))->sort()->implode(' ');
    }
}

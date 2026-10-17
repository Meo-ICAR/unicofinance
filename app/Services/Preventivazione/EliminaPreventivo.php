<?php

namespace App\Services\Preventivazione;

use App\Models\FinancingQuote;
use Illuminate\Support\Facades\DB;

/** Elimina un preventivo non ancora perfezionato insieme alla sua pratica in stato PREVENTIVO o SOSPESA, se nessun istruttore l'ha presa in carico. */
class EliminaPreventivo
{
    public function elimina(FinancingQuote $quote): bool
    {
        $pratica = $quote->pratica;

        if ($quote->status !== 'salvato' || ($pratica !== null && ($pratica->employee_id !== null || ($pratica->praticaStato?->codice !== 'PREVENTIVO' && ! $pratica->praticaStato?->is_suspended)))) {
            return false;
        }

        DB::transaction(function () use ($quote, $pratica): void {
            $quote->delete();
            $pratica?->delete();
        });

        return true;
    }
}

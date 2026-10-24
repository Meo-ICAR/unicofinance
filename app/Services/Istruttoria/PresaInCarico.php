<?php

namespace App\Services\Istruttoria;

use App\Models\User;
use App\Services\CambioStatoPratica;
use App\Services\Preventivazione\PracticeFinalizer;
use App\Support\Istruttori;
use Illuminate\Database\Eloquent\Builder;
use Unico\Core\Models\Employee;
use Unico\Core\Models\Pratica;

/**
 * Presa in carico di una pratica da parte di un istruttore: la pratica passa dallo stato «INVIO IN ISTRUTTORIA» a «PRESA IN CARICO»
 * (con storico), l'istruttore diventa `employee_id` e si valorizza `presa_in_carico_at`. Da «PRESA IN CARICO» in poi il produttore non
 * modifica più la pratica: il blocco dipende dallo stato (`config('istruttoria.stati_modificabili_dal_produttore')`), non da
 * `employee_id`, così l'istruttore assegnato in automatico alla perfezione non toglie ancora la pratica al produttore.
 */
class PresaInCarico
{
    public const STATO = 'PRESA IN CARICO';

    public function __construct(private CambioStatoPratica $stati, private PracticeFinalizer $finalizzatore) {}

    /** Il produttore può ancora modificare la pratica: stato prima della presa in carico e presa in carico non ancora avvenuta. */
    public function modificabileDalProduttore(Pratica $pratica): bool
    {
        if ($pratica->presa_in_carico_at !== null) {
            return false;
        }

        $codice = $pratica->praticaStato?->codice;

        return $codice === null || in_array(mb_strtoupper($codice), self::statiModificabili(), true);
    }

    /**
     * Le pratiche che il produttore non modifica più (da «PRESA IN CARICO» in poi).
     *
     * @param  Builder<Pratica>  $query
     * @return Builder<Pratica>
     */
    public static function bloccateAlProduttore(Builder $query): Builder
    {
        return $query->where(fn (Builder $pratiche) => $pratiche
            ->whereNotNull('presa_in_carico_at')
            ->orWhereHas('praticaStato', fn (Builder $stato) => $stato->whereNotIn('codice', self::statiModificabili())));
    }

    /**
     * Presa in carico da parte di un istruttore. Lo stato cambia solo se la pratica è ancora prima della presa in carico.
     */
    public function prendi(Pratica $pratica, Employee $istruttore, string $origine, ?string $motivo = null): void
    {
        $dati = ['employee_id' => $istruttore->getKey()];

        if ($pratica->presa_in_carico_at === null) {
            $dati['presa_in_carico_at'] = today();
        }

        $prima = $this->primaDellaPresa($pratica);
        $pratica->update($dati);

        if ($prima) {
            $stato = $this->finalizzatore->status((string) $pratica->tipoprodotto?->name, self::STATO);
            $this->stati->cambia($pratica, $stato->getKey(), $motivo ?? 'Presa in carico da '.$istruttore->name, $origine);
        }
    }

    /**
     * L'istruttore apre la pratica che aspetta in istruttoria: la prende in carico. Se è assegnata a un altro istruttore, o chi apre
     * non è un istruttore, non succede nulla.
     */
    public function allApertura(?User $utente, Pratica $pratica): void
    {
        $istruttore = $utente === null ? null : Employee::query()->where('user_id', $utente->getKey())->first();

        if ($istruttore === null || ! Istruttori::e($istruttore)) {
            return;
        }

        if (mb_strtoupper((string) $pratica->praticaStato?->codice) !== InvioInIstruttoria::STATO) {
            return;
        }

        if ($pratica->employee_id !== null && (int) $pratica->employee_id !== (int) $istruttore->getKey()) {
            return;
        }

        $this->prendi($pratica, $istruttore, 'istruttoria');
    }

    /** Stato in cui la pratica è ancora prima della presa in carico (senza «SOSPESA», che può essere anche successiva). */
    private function primaDellaPresa(Pratica $pratica): bool
    {
        $codice = mb_strtoupper((string) $pratica->praticaStato?->codice);

        return $codice !== 'SOSPESA' && ($codice === '' || in_array($codice, self::statiModificabili(), true));
    }

    /**
     * @return list<string>
     */
    public static function statiModificabili(): array
    {
        return array_map('mb_strtoupper', (array) config('istruttoria.stati_modificabili_dal_produttore', []));
    }
}

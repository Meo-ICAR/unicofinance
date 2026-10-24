<?php

namespace App\Services\Istruttoria;

use App\Models\Pratica as PraticaApp;
use App\Services\Agenti\DipendenteDelloAgente;
use App\Support\Istruttori;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Unico\Core\Models\Employee;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;

/**
 * Assegna l'istruttore a una pratica quando l'agente la perfeziona. Se l'agente è anche un dipendente (`DipendenteDelloAgente`:
 * stesso utente, codice fiscale o nome) la pratica va a lui; altrimenti si guarda la specializzazione registrata nell'anagrafica del dipendente (`tipoprodotto_ids` e `clienti_ids`): chi segue la banca della pratica ha la precedenza su chi segue il prodotto, e
 * questi su chi non ha specializzazioni. A parità va a chi ha meno pratiche in corso. Chi è specializzato solo su altro non viene
 * preso in considerazione. Se la pratica ha già un istruttore lo si cambia soltanto quando la banca, scelta dopo la perfezione,
 * ha uno specialista e l'attuale non lo è.
 */
class AssegnaIstruttore
{
    /** Ruolo del dipendente che istruisce le pratiche (stesso di chi riceve l'invio in istruttoria). */
    public const RUOLO = Istruttori::RUOLO;

    private const PUNTI_BANCA = 4;

    private const PUNTI_PRODOTTO = 2;

    private const PUNTI_GENERICO = 1;

    public function assegna(Pratica $pratica): ?Employee
    {
        $agente = $this->dipendenteDelloAgente($pratica);

        if ($agente !== null) {
            if ($pratica->employee_id !== $agente->getKey()) {
                $pratica->update(['employee_id' => $agente->getKey()]);
            }

            return $agente;
        }

        $scelto = $this->scegli($pratica);

        if ($scelto === null) {
            return null;
        }

        $attuale = $pratica->employee_id === null ? null : Employee::query()->find($pratica->employee_id);

        if ($attuale === null || ($scelto->getKey() !== $attuale->getKey() && $this->seguelaBanca($scelto, $pratica) && ! $this->seguelaBanca($attuale, $pratica))) {
            $pratica->update(['employee_id' => $scelto->getKey()]);
        }

        return $scelto;
    }

    /** Il dipendente che è anche l'agente della pratica (vedi DipendenteDelloAgente). */
    public function dipendenteDelloAgente(Pratica $pratica): ?Employee
    {
        $agente = $pratica->fornitori_id === null ? null : Fornitore::query()->find($pratica->fornitori_id);

        return $agente === null ? null : app(DipendenteDelloAgente::class)->trova($agente);
    }

    private function scegli(Pratica $pratica): ?Employee
    {
        $candidati = $this->istruttori()
            ->map(fn (Employee $istruttore): array => ['istruttore' => $istruttore, 'punti' => $this->punti($istruttore, $pratica)])
            ->filter(fn (array $candidato): bool => $candidato['punti'] > 0);

        if ($candidati->isEmpty()) {
            return null;
        }

        $massimo = $candidati->max('punti');

        return $candidati->where('punti', $massimo)
            ->sortBy([
                fn (array $a, array $b): int => $this->carico($a['istruttore']) <=> $this->carico($b['istruttore']),
                fn (array $a, array $b): int => $a['istruttore']->getKey() <=> $b['istruttore']->getKey(),
            ])
            ->first()['istruttore'];
    }

    /**
     * @return Collection<int, Employee>
     */
    private function istruttori(): Collection
    {
        return Employee::query()
            ->where('is_active', true)
            ->tap(fn (Builder $query) => Istruttori::scope($query))
            ->get();
    }

    private function punti(Employee $istruttore, Pratica $pratica): int
    {
        $segueProdotto = $this->contiene($istruttore->tipoprodotto_ids, $pratica->tipoprodotto_id);
        $segueBanca = $this->seguelaBanca($istruttore, $pratica);

        if (blank($istruttore->tipoprodotto_ids) && blank($istruttore->clienti_ids)) {
            return self::PUNTI_GENERICO;
        }

        return ($segueBanca ? self::PUNTI_BANCA : 0) + ($segueProdotto ? self::PUNTI_PRODOTTO : 0);
    }

    private function seguelaBanca(Employee $istruttore, Pratica $pratica): bool
    {
        return $this->contiene($istruttore->clienti_ids, $pratica->clienti_id);
    }

    /**
     * @param  array<int, int|string>|null  $elenco
     */
    private function contiene(?array $elenco, mixed $id): bool
    {
        return $id !== null && in_array((int) $id, array_map('intval', $elenco ?? []), true);
    }

    private function carico(Employee $istruttore): int
    {
        return PraticaApp::query()->inCorso()->where('employee_id', $istruttore->getKey())->count();
    }
}

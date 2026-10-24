<?php

namespace App\Services\Istruttoria;

use App\Models\Pratica;
use Unico\Core\Models\Employee;

/**
 * Per alcuni produttori che sono anche dipendenti (`config('istruttoria.istruttore_forzato_dal_produttore')`, per esempio FUMMO BRIGIDA e
 * ZACCARIA LOREDANA) l'istruttore di ogni loro pratica è sempre il produttore stesso: si forza anche se ne era già indicato un altro.
 * Il produttore si riconosce dal nome (senza badare a maiuscole, spazi e ordine), dall'anagrafica `fornitori` o da `denominazione_agente`.
 */
class IstruttoreDelProduttore
{
    /** Forza l'istruttore della pratica se il suo produttore è uno di quelli indicati; vero se l'ha cambiato. */
    public function forza(Pratica $pratica): bool
    {
        $dipendenti = $this->dipendenti();
        $istruttore = null;

        foreach ([$pratica->fornitori?->name, $pratica->denominazione_agente] as $nome) {
            $nome = $this->nome((string) $nome);

            if ($nome !== '' && isset($dipendenti[$nome])) {
                $istruttore = $dipendenti[$nome];

                break;
            }
        }

        if ($istruttore === null || (int) $pratica->employee_id === (int) $istruttore->getKey()) {
            return false;
        }

        $pratica->forceFill(['employee_id' => $istruttore->getKey()])->save();

        return true;
    }

    /** @return int pratiche cambiate */
    public function forzaTutte(): int
    {
        $cambiate = 0;

        foreach (array_keys($this->dipendenti()) as $nome) {
            $cognomi = array_filter(explode(' ', $nome), fn (string $parte): bool => strlen($parte) > 2);

            Pratica::query()
                ->with('fornitori')
                ->where(function ($query) use ($cognomi): void {
                    foreach ($cognomi as $parte) {
                        $query->orWhere('denominazione_agente', 'like', "%{$parte}%")
                            ->orWhereHas('fornitori', fn ($fornitore) => $fornitore->where('name', 'like', "%{$parte}%"));
                    }
                })
                ->each(function (Pratica $pratica) use (&$cambiate): void {
                    $cambiate += $this->forza($pratica) ? 1 : 0;
                });
        }

        return $cambiate;
    }

    /**
     * Dipendenti dei produttori indicati, per nome normalizzato.
     *
     * @return array<string, Employee>
     */
    private function dipendenti(): array
    {
        $indicati = array_map(fn (string $nome): string => $this->nome($nome), (array) config('istruttoria.istruttore_forzato_dal_produttore', []));

        return Employee::query()->where('is_active', true)->orderBy('id')->get()
            ->filter(fn (Employee $dipendente): bool => in_array($this->nome((string) $dipendente->name), $indicati, true))
            ->unique(fn (Employee $dipendente): string => $this->nome((string) $dipendente->name))
            ->mapWithKeys(fn (Employee $dipendente): array => [$this->nome((string) $dipendente->name) => $dipendente])
            ->all();
    }

    /** Nome senza maiuscole, spazi doppi e ordine: «FUMMO BRIGIDA» e «Brigida  Fummo» coincidono. */
    private function nome(string $nome): string
    {
        return collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($nome), -1, PREG_SPLIT_NO_EMPTY))->sort()->implode(' ');
    }
}

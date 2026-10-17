<?php

namespace App\Services\Provvigioni;

use App\Models\Pratica;
use App\Models\Provvigione;

/**
 * Percentuale dei compensi di una pratica. Il compenso dell'istituto (tipo «Istituto», in entrata) è la percentuale della base indicata
 * dal prodotto (`tipoprodotto.tipo_provvigioni`: lordo, erogato, netto o interessi); quello dell'agente (tipo «Agente», in uscita) è la
 * percentuale delle provvigioni attive della pratica; quello del cliente (tipo «Cliente», la mediazione) è la percentuale indicata sulla
 * pratica. Senza base, o con base zero, la percentuale resta vuota.
 */
class PercentualeProvvigione
{
    /** Base di calcolo della pratica secondo il prodotto; null se il prodotto non la indica o la pratica non ha il dato. */
    public function base(Pratica $pratica): ?float
    {
        // Le pratiche importate da Mediafacile hanno il montante (`amount`) e l'importo erogato (`net`) al posto di rata, durata ed erogato.
        $lordo = $pratica->rata !== null && $pratica->nrate !== null ? (float) $pratica->rata * (int) $pratica->nrate : ($pratica->amount !== null ? (float) $pratica->amount : null);
        $erogato = $pratica->erogato !== null ? (float) $pratica->erogato : ($pratica->net !== null ? (float) $pratica->net : null);

        // Cessione e delega: l'erogato è rata per durata.
        if (in_array($pratica->tipoprodotto?->name, ['Cessione', 'Delega'], true) && $pratica->rata !== null && $pratica->nrate !== null) {
            $erogato = (float) $pratica->rata * (int) $pratica->nrate;
        }

        $base = match (strtolower((string) $pratica->tipoprodotto?->tipo_provvigioni)) {
            'lordo' => $lordo,
            'erogato' => $erogato,
            'netto' => $pratica->net !== null ? (float) $pratica->net : null,
            'interessi' => $lordo !== null && $erogato !== null ? $lordo - $erogato : null,
            default => null,
        };

        return $base !== null && $base > 0 ? $base : null;
    }

    /**
     * Ricalcola la percentuale dei compensi delle pratiche indicate (per codice).
     *
     * @param  iterable<int, string>  $codiciPratica
     * @return int compensi aggiornati
     */
    public function ricalcola(iterable $codiciPratica): int
    {
        $aggiornati = 0;

        foreach (collect($codiciPratica)->filter()->unique()->chunk(200) as $codici) {
            $pratiche = Pratica::query()->with('tipoprodotto')->whereIn('codice_pratica', $codici->all())->get()->keyBy('codice_pratica');
            $compensi = Provvigione::query()->whereIn('id_pratica', $codici->all())->get()->groupBy('id_pratica');

            foreach ($compensi as $codice => $righe) {
                $pratica = $pratiche->get($codice);
                $attive = $righe->filter(fn (Provvigione $riga): bool => $this->eAttiva($riga) && ! $riga->annullato);
                $totaleAttive = (float) $attive->sum('importo');
                $base = $pratica === null ? null : $this->base($pratica);

                foreach ($righe as $riga) {
                    $percentuale = match (true) {
                        $this->eAttiva($riga) => $base === null ? null : (float) $riga->importo / $base * 100,
                        $this->eDellAgente($riga) => $totaleAttive > 0 ? (float) $riga->importo / $totaleAttive * 100 : null,
                        $this->eDelCliente($riga) => $this->percentualeCliente($pratica),
                        default => $riga->percentuale === null ? null : (float) $riga->percentuale,
                    };
                    // La colonna è decimal(7,4): una percentuale fuori scala (base minuscola, storni) non ha senso e resta vuota.
                    $percentuale = $percentuale === null || abs($percentuale) >= 1000 ? null : round($percentuale, 4);

                    if ($percentuale !== ($riga->percentuale === null ? null : round((float) $riga->percentuale, 4))) {
                        Provvigione::query()->whereKey($riga->getKey())->update(['percentuale' => $percentuale]);
                        $aggiornati++;
                    }
                }
            }
        }

        return $aggiornati;
    }

    /** La mediazione richiesta al cliente: la percentuale scritta sulla pratica, altrimenti l'importo sull'importo richiesto. */
    private function percentualeCliente(?Pratica $pratica): ?float
    {
        if ($pratica === null) {
            return null;
        }

        if (filled($pratica->mediation_fee_percent)) {
            return (float) $pratica->mediation_fee_percent;
        }

        return (float) $pratica->mediation_fee_amount > 0 && (float) $pratica->amount > 0 ? (float) $pratica->mediation_fee_amount / (float) $pratica->amount * 100 : null;
    }

    private function eDelCliente(Provvigione $riga): bool
    {
        return $riga->tipo === 'Cliente';
    }

    private function eAttiva(Provvigione $riga): bool
    {
        return $riga->entrata_uscita === 'Entrata' && $riga->tipo === 'Istituto';
    }

    private function eDellAgente(Provvigione $riga): bool
    {
        return $riga->entrata_uscita === 'Uscita' && $riga->tipo === 'Agente';
    }
}

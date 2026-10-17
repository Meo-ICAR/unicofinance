<?php

namespace App\Services\Provvigioni;

use App\Models\Pratica;
use App\Models\Provvigione;

/**
 * Il compenso di tipo «Cliente» (in entrata) è la mediazione chiesta al cliente: se la pratica non ha ancora la mediazione (né importo
 * né percentuale), lo si riporta sulla pratica (e la percentuale sull'importo richiesto, se c'è). Una mediazione già indicata non si tocca.
 */
class MediazioneDaCompenso
{
    /**
     * @param  iterable<int, string>  $codiciPratica
     * @return int pratiche aggiornate
     */
    public function riporta(iterable $codiciPratica): int
    {
        $aggiornate = 0;

        foreach (collect($codiciPratica)->filter()->unique()->chunk(200) as $codici) {
            $importi = Provvigione::query()
                ->whereIn('id_pratica', $codici->all())
                ->where('tipo', 'Cliente')
                ->where('entrata_uscita', 'Entrata')
                ->where(fn ($query) => $query->where('annullato', false)->orWhereNull('annullato'))
                ->get()
                ->groupBy('id_pratica')
                ->map(fn ($righe): float => (float) $righe->sum('importo'));

            foreach (Pratica::query()->whereIn('codice_pratica', $importi->keys()->all())->get() as $pratica) {
                $importo = $importi->get($pratica->codice_pratica, 0.0);

                if ($importo <= 0 || (float) $pratica->mediation_fee_amount > 0 || (float) $pratica->mediation_fee_percent > 0) {
                    continue;
                }

                $pratica->mediation_fee_amount = round($importo, 2);

                if (blank($pratica->mediation_fee_percent) && (float) $pratica->amount > 0) {
                    $pratica->mediation_fee_percent = round($importo / (float) $pratica->amount * 100, 2);
                }

                $pratica->save();
                $aggiornate++;
            }
        }

        return $aggiornate;
    }
}

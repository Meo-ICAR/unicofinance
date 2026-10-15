<?php

namespace App\Services;

use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaStato;

/**
 * Cambio di stato di una pratica: storico degli stati (`pratica_status_history`) con la motivazione e, se il nuovo stato lo
 * prevede, data di oggi su rifiuto, invio in banca, approvazione o erogazione (senza sovrascrivere date già presenti).
 */
class CambioStatoPratica
{
    private const DATE = ['is_rejected' => 'rejected_at', 'is_sended' => 'sended_at', 'is_approved' => 'approved_at', 'is_erogated' => 'erogated_at'];

    /** Porta la pratica nel nuovo stato e lo registra. */
    public function cambia(Pratica $pratica, int|string|null $nuovo, ?string $motivo, string $origine): void
    {
        $precedente = $pratica->pratica_stato_id;

        if ((string) $precedente === (string) $nuovo) {
            return;
        }

        $pratica->pratica_stato_id = $nuovo;
        $pratica->save();

        $this->registra($pratica, $precedente, $nuovo, $motivo, $origine);
    }

    /**
     * Scrive lo storico e valorizza le date di uno stato già impostato sulla pratica.
     *
     * @return array<int, string> campi data valorizzati
     */
    public function registra(Pratica $pratica, int|string|null $precedente, int|string|null $nuovo, ?string $motivo, string $origine): array
    {
        if ((string) $precedente === (string) $nuovo) {
            return [];
        }

        $pratica->praticaStatusHistory()->create([
            'status_from' => $precedente === null ? null : PraticaStato::query()->whereKey($precedente)->value('name'),
            'status_to' => $nuovo === null ? '' : (string) PraticaStato::query()->whereKey($nuovo)->value('name'),
            'changed_at' => now(),
            'source' => $origine,
            'notes' => $motivo,
            'user_id' => auth()->id(),
        ]);

        return $this->valorizzaDate($pratica, $nuovo);
    }

    /** @return array<int, string> */
    private function valorizzaDate(Pratica $pratica, int|string|null $statoId): array
    {
        $stato = $statoId === null ? null : PraticaStato::query()->find($statoId);
        $date = [];

        foreach (self::DATE as $flag => $campo) {
            if ($stato?->{$flag} && blank($pratica->{$campo})) {
                $date[$campo] = today();
            }
        }

        if ($date !== []) {
            $pratica->forceFill($date)->save();
        }

        return array_keys($date);
    }
}

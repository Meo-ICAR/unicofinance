<?php

namespace App\Services;

use App\Services\Istruttoria\CompletezzaDocumentale;
use Illuminate\Support\Collection;
use Unico\Core\Models\DocumentRequirement;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaStato;

/**
 * Cambio di stato di una pratica: storico degli stati (`pratica_status_history`) con la motivazione e, se il nuovo stato lo
 * prevede, data di oggi su rifiuto, invio in banca, approvazione o erogazione (senza sovrascrivere date già presenti).
 */
class CambioStatoPratica
{
    private const DATE = ['is_rejected' => 'rejected_at', 'is_sended' => 'sended_at', 'is_approved' => 'approved_at', 'is_erogated' => 'erogated_at'];

    /**
     * Documenti obbligatori che mancano per portare la pratica in questo stato; null se lo stato non richiede la documentazione
     * completa (`config('istruttoria.stati_controllati')`).
     *
     * @return Collection<int, DocumentRequirement>|null
     */
    public function documentiMancanti(Pratica $pratica, int|string|null $nuovo): ?Collection
    {
        $nome = $nuovo === null ? null : PraticaStato::query()->whereKey($nuovo)->value('name');

        if ($nome === null || ! in_array($nome, config('istruttoria.stati_controllati', []), true)) {
            return null;
        }

        return app(CompletezzaDocumentale::class)->per($pratica)['mancanti'];
    }

    /** Se la documentazione incompleta ferma il cambio di stato (di default no: serve a recuperare lo storico). */
    public function documentazioneBloccante(): bool
    {
        return (bool) config('istruttoria.documentazione_bloccante');
    }

    /** Messaggio per l'operatore sui documenti mancanti. */
    public function messaggioMancanti(Collection $mancanti): string
    {
        return $mancanti->take(5)->map(fn (DocumentRequirement $requisito): string => (string) $requisito->documentType?->name)->implode(', ').($mancanti->count() > 5 ? ' e altri '.($mancanti->count() - 5) : '');
    }

    /** Motivazione scritta nello storico quando il produttore dichiara la rinuncia del cliente. */
    public const MOTIVO_RINUNCIA_PRODUTTORE = 'Rinuncia cliente da produttore';

    /** Lo stato «RINUNCIA CLIENTE» del tipo di finanziamento della pratica (altrimenti il primo con quel codice). */
    public function statoRinuncia(Pratica $pratica): ?PraticaStato
    {
        $stati = PraticaStato::query()->where('codice', 'RINUNCIA CLIENTE')->orderBy('id')->get();
        $prodotto = $pratica->tipoprodotto?->name;

        return $stati->first(fn (PraticaStato $stato): bool => $prodotto !== null && strcasecmp((string) $stato->tipo_finanziamento, $prodotto) === 0) ?? $stati->first();
    }

    /**
     * Il produttore dichiara la rinuncia del cliente: la pratica passa a «RINUNCIA CLIENTE» e lo storico riporta la motivazione.
     *
     * @return bool falso se non esiste lo stato o la pratica è già in rinuncia
     */
    public function rinunciaCliente(Pratica $pratica, ?string $nota = null): bool
    {
        $stato = $this->statoRinuncia($pratica);

        if ($stato === null || (string) $pratica->pratica_stato_id === (string) $stato->getKey()) {
            return false;
        }

        $this->cambia($pratica, $stato->getKey(), trim(self::MOTIVO_RINUNCIA_PRODUTTORE.($nota ? ': '.$nota : '')), 'agenti');

        return true;
    }

    /** Lo stato «SOSPESA» del tipo di finanziamento della pratica (altrimenti il primo stato sospeso con quel codice). */
    public function statoSospesa(Pratica $pratica): ?PraticaStato
    {
        return $this->statoDelProdotto($pratica, 'SOSPESA');
    }

    /** Lo stato «PREVENTIVO» a cui torna una pratica ripresa. */
    public function statoPreventivo(Pratica $pratica): ?PraticaStato
    {
        return $this->statoDelProdotto($pratica, 'PREVENTIVO');
    }

    public function eSospesa(Pratica $pratica): bool
    {
        return (bool) $pratica->praticaStato?->is_suspended;
    }

    /** Il produttore sospende il preventivo: la pratica passa a «SOSPESA». */
    public function sospendi(Pratica $pratica, ?string $nota = null): bool
    {
        $stato = $this->statoSospesa($pratica);

        if ($stato === null || $this->eSospesa($pratica)) {
            return false;
        }

        $this->cambia($pratica, $stato->getKey(), trim('Sospesa dal produttore'.($nota ? ': '.$nota : '')), 'agenti');

        return true;
    }

    /** Il produttore riprende il preventivo sospeso: la pratica torna a «PREVENTIVO». */
    public function riprendi(Pratica $pratica): bool
    {
        $stato = $this->statoPreventivo($pratica);

        if ($stato === null || ! $this->eSospesa($pratica)) {
            return false;
        }

        $this->cambia($pratica, $stato->getKey(), 'Ripresa dal produttore', 'agenti');

        return true;
    }

    private function statoDelProdotto(Pratica $pratica, string $codice): ?PraticaStato
    {
        $stati = PraticaStato::query()->where('codice', $codice)->orderBy('id')->get();
        $prodotto = $pratica->tipoprodotto?->name;

        return $stati->first(fn (PraticaStato $stato): bool => $prodotto !== null && strcasecmp((string) $stato->tipo_finanziamento, $prodotto) === 0) ?? $stati->first();
    }

    /** L'istruttore prende visione dei cambi di stato non fatti da lui. */
    public function presaVisione(Pratica $pratica): int
    {
        return $pratica->praticaStatusHistory()->daVisionare()->update(['seen_at' => now(), 'seen_by' => auth()->id()]);
    }

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
            'notes' => filled($motivo) ? trim($motivo) : null,
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

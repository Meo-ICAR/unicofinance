<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlacklistClienteFornitore;
use App\Models\Pratica;
use App\Services\Istruttoria\CompletezzaDocumentale;
use Illuminate\Http\JsonResponse;

/**
 * Dati di una pratica per unicobpm, che non ha un proprio modello Pratica: stato con i suoi flag, agente, banca, istruttore e
 * blacklist dell'agente per la banca (`agente_blacklistato`, letto dall'azione blacklist_check). I campi `stato_pratica`,
 * `denominazione_agente` e `denominazione_banca` restano come li esponeva unicoloan.
 */
class PraticaApiController extends Controller
{
    public function show(int $id, CompletezzaDocumentale $completezza): JsonResponse
    {
        $pratica = Pratica::query()->with(['praticaStato', 'tipoprodotto', 'fornitori', 'clienti'])->find($id);

        if ($pratica === null) {
            return response()->json(['message' => 'Pratica non trovata.'], 404);
        }

        $stato = $pratica->praticaStato;
        $documenti = $completezza->per($pratica);

        return response()->json([
            'id' => $pratica->getKey(),
            'codice_pratica' => $pratica->codice_pratica,
            'codice_pratica_banca' => $pratica->codice_pratica_banca,
            'company_id' => $pratica->company_id,
            'tipoprodotto_id' => $pratica->tipoprodotto_id,
            'tipoprodotto' => $pratica->tipoprodotto?->name,
            'pratica_stato_id' => $pratica->pratica_stato_id,
            'stato_pratica' => $stato?->name,
            'stato_flags' => [
                'is_sended' => (bool) $stato?->is_sended,
                'is_approved' => (bool) $stato?->is_approved,
                'is_erogated' => (bool) $stato?->is_erogated,
                'is_rejected' => (bool) $stato?->is_rejected,
                'is_suspended' => (bool) $stato?->is_suspended,
            ],
            'fornitori_id' => $pratica->fornitori_id,
            'denominazione_agente' => $pratica->denominazione_agente ?? $pratica->fornitori?->name,
            'clienti_id' => $pratica->clienti_id,
            'denominazione_banca' => $pratica->denominazione_banca ?? $pratica->clienti?->name,
            'employee_id' => $pratica->employee_id,
            'is_notowned' => (bool) $pratica->is_notowned,
            'date' => [
                'sended_at' => $pratica->sended_at?->toDateString(),
                'approved_at' => $pratica->approved_at?->toDateString(),
                'erogated_at' => $pratica->erogated_at?->toDateString(),
                'rejected_at' => $pratica->rejected_at?->toDateString(),
            ],
            'agente_blacklistato' => $this->agenteBlacklistato($pratica),
            'documenti' => [
                'obbligatori' => $documenti['totale'],
                'presenti' => $documenti['presenti'],
                'mancanti' => $documenti['mancanti']->count(),
            ],
        ]);
    }

    /** L'istituto della pratica non lavora con il suo agente: divieto in corso (senza fine o non ancora finito). */
    private function agenteBlacklistato(Pratica $pratica): bool
    {
        if ($pratica->fornitori_id === null || $pratica->clienti_id === null) {
            return false;
        }

        return BlacklistClienteFornitore::query()
            ->where('cliente_id', $pratica->clienti_id)
            ->where('fornitore_id', $pratica->fornitori_id)
            ->where(fn ($query) => $query->whereNull('data_inizio')->orWhereDate('data_inizio', '<=', today()))
            ->where(fn ($query) => $query->whereNull('data_fine')->orWhereDate('data_fine', '>=', today()))
            ->exists();
    }
}

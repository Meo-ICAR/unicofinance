<?php

namespace App\Services\Istruttoria;

use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Branch;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentRequest;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Pratica;

/**
 * Impegno di terzi con l'istituto che lo ha erogato: al salvataggio si richiede il «Conto estintivo», ma solo se l'istituto ha
 * una filiale Estinzioni (il nome contiene «Estinzion»), a cui chiederlo. L'istituto può non essere attivo.
 *
 * Il flusso: il modulo «Richiesta Conteggio Estintivo» (da stampare) parte verso l'istituto; l'istituto risponde con il «Conto estintivo»
 * (richiesto sulla pratica), che l'istruttore verifica e poi invia in banca (vedi InvioContoEstintivo).
 */
class RichiestaContoEstintivo
{
    public const TIPO_DOCUMENTO = 'CONTO_ESTINTIVO';

    public const TIPO_RICHIESTA = 'Richiesta Conteggio Estintivo';

    public const FILIALE = 'Estinzion';

    /**
     * @return string|null il motivo per cui il conto estintivo non è stato richiesto; null se richiesto o non pertinente
     */
    public function sincronizza(Pratica $pratica): ?string
    {
        if (! $pratica->is_notowned || $pratica->clienti_id === null) {
            return null;
        }

        if (! $this->haFilialeEstinzioni($pratica->clienti_id)) {
            return "L'istituto non ha una filiale Estinzioni: il conto estintivo non è stato richiesto.";
        }

        $tipo = DocumentType::query()->where('code', self::TIPO_DOCUMENTO)->first();

        if ($tipo === null) {
            return 'Il tipo documento «Conto estintivo» non esiste: non è stato richiesto.';
        }

        $this->creaRichiesta($pratica);

        DocumentRequest::query()->firstOrCreate(
            ['requestable_type' => 'pratica', 'requestable_id' => (string) $pratica->getKey(), 'document_type_id' => $tipo->getKey()],
            [
                'name' => $tipo->name,
                'company_id' => $pratica->company_id,
                'requested_by_role' => 'backoffice',
                'assigned_to_type' => 'agent',
                'status' => 'richiesto',
                'is_required' => true,
                'requested_at' => now(),
                'created_by' => auth()->id(),
            ],
        );

        return null;
    }

    public function haFilialeEstinzioni(int|string $istitutoId): bool
    {
        return Branch::query()
            ->where('branchable_type', 'cliente')
            ->where('branchable_id', (string) $istitutoId)
            ->where('name', 'like', '%'.self::FILIALE.'%')
            ->exists();
    }

    /** Il modulo di richiesta del conteggio all'istituto, da stampare e inviare (una sola volta per pratica). */
    private function creaRichiesta(Pratica $pratica): void
    {
        $tipo = DocumentType::query()->where('name', self::TIPO_RICHIESTA)->first();

        if ($tipo === null) {
            return;
        }

        Document::query()->firstOrCreate(
            ['documentable_type' => 'pratica', 'documentable_id' => (string) $pratica->getKey(), 'document_type_id' => $tipo->getKey()],
            ['name' => $tipo->name, 'company_id' => $pratica->company_id, 'status' => DocumentStatus::PRINT],
        );
    }
}

<?php

namespace App\Services\Istruttoria;

use App\Models\Pratica;
use Illuminate\Support\Collection;
use Unico\Core\Documents\PracticeTaskDocuments;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentRequirement;
use Unico\Core\Models\TipoProdottoSub;

/**
 * Documenti obbligatori di una pratica: check list del prodotto (per gruppo di tipologia lavorativa del richiedente) più
 * fascicolo da firmare, filtrati per banca. Un requisito è soddisfatto da un documento caricato, già OK, provvisorio o N/A.
 */
class CompletezzaDocumentale
{
    public function __construct(private PracticeTaskDocuments $documents) {}

    /** @return array{totale: int, presenti: int, mancanti: Collection<int, DocumentRequirement>} */
    public function per(Pratica $pratica): array
    {
        $requisiti = $this->requisiti($pratica);

        $stati = Document::query()
            ->where('documentable_type', 'pratica')
            ->where('documentable_id', (string) $pratica->getKey())
            ->whereIn('document_type_id', $requisiti->pluck('document_type_id'))
            ->get(['document_type_id', 'status'])
            ->groupBy('document_type_id');

        $mancanti = $requisiti->reject(fn (DocumentRequirement $requisito): bool => $stati->get($requisito->document_type_id, collect())
            ->contains(fn (Document $document): bool => $document->status instanceof DocumentStatus && $document->status->soddisfa()))->values();

        return ['totale' => $requisiti->count(), 'presenti' => $requisiti->count() - $mancanti->count(), 'mancanti' => $mancanti];
    }

    /** @return Collection<int, DocumentRequirement> */
    private function requisiti(Pratica $pratica): Collection
    {
        $prodotto = $pratica->tipoprodotto?->name;

        if ($prodotto === null) {
            return collect();
        }

        return $this->documents->forProduct($prodotto, $this->gruppo($pratica), $pratica->clienti_id)
            ->concat($this->documents->forProductStatus($prodotto, 'FASCICOLO COMPLETO', $pratica->clienti_id))
            ->filter(fn (DocumentRequirement $requisito): bool => $requisito->is_required)
            ->unique('document_type_id')
            ->values();
    }

    /** Gruppo documentale della tipologia lavorativa del richiedente (pensionati, privati, pubblici, parapubblici, autonomi). */
    private function gruppo(Pratica $pratica): ?string
    {
        $tipologia = $pratica->richiedente?->employment_typology;

        if (blank($tipologia)) {
            return null;
        }

        $gruppo = TipoProdottoSub::query()->where('name', $tipologia)->value('employment_document_group');

        return $gruppo instanceof \BackedEnum ? $gruppo->value : ($gruppo === null ? null : (string) $gruppo);
    }
}

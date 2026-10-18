<?php

namespace App\Services\Istruttoria;

use App\Mail\ContoEstintivoInBanca;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\Pratica;

/** Dopo che l'istruttore ha verificato (approvato) il conto estintivo ricevuto dall'istituto, lo si invia in banca per email. */
class InvioContoEstintivo
{
    /** Il conto estintivo della pratica, se è stato approvato dall'istruttore. */
    public function documentoApprovato(Pratica $pratica): ?Document
    {
        return Document::query()
            ->where('documentable_type', 'pratica')
            ->where('documentable_id', (string) $pratica->getKey())
            ->where('status', DocumentStatus::APPROVED)
            ->whereHas('documentType', fn ($tipo) => $tipo->where('code', RichiestaContoEstintivo::TIPO_DOCUMENTO))
            ->latest('id')
            ->first();
    }

    /**
     * @throws RuntimeException se il conto estintivo non è stato approvato o non ha un file
     */
    public function invia(Pratica $pratica, string $email): Document
    {
        $documento = $this->documentoApprovato($pratica);

        if ($documento === null || $documento->currentMedia() === null) {
            throw new RuntimeException('Il conto estintivo non è ancora stato caricato e approvato.');
        }

        Mail::to($email)->send(new ContoEstintivoInBanca($pratica, $documento));

        Document::query()->whereKey($documento->getKey())->update(['last_sent_at' => now()]);

        return $documento->fresh();
    }
}

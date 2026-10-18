<?php

namespace App\Services\Agenti;

use App\Models\Client;
use App\Models\Pratica;
use App\Services\Istruttoria\RichiedentePratica;
use App\Services\Preventivazione\PracticeFinalizer;
use Illuminate\Http\UploadedFile;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\PdfModule;
use Unico\Core\Models\TipoProdotto;
use Unico\Core\Pdf\ModulePrinter;
use Unico\Core\Pdf\PdfFormException;
use Unico\Core\Signature\SignatureRequestException;
use Unico\Core\Signature\SignatureRequestService;
use Unico\Core\Signature\SignerInput;

/**
 * Accoglie una richiesta di unicoagent: cliente e pratica (idempotenti per riferimento), poi i file.
 * Il codice pratica è WA- seguito dal riferimento di unicoagent.
 */
class AgentRequestIntake
{
    public function code(string $riferimento): string
    {
        return 'WA-'.$riferimento;
    }

    public function find(string $riferimento): ?Pratica
    {
        return Pratica::query()->where('codice_pratica', $this->code($riferimento))->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{pratica: Pratica, created: bool}|array{error: string, status: int}
     */
    public function receive(array $payload): array
    {
        $riferimento = trim((string) ($payload['riferimento'] ?? ''));
        $cliente = (array) ($payload['cliente'] ?? []);
        $agente = (array) ($payload['agente'] ?? []);
        $fiscale = strtoupper(preg_replace('/\s+/', '', (string) ($cliente['codice_fiscale'] ?? '')));
        $piva = preg_replace('/\D+/', '', (string) ($agente['partita_iva'] ?? ''));

        if ($riferimento === '' || ! in_array(strlen($fiscale), [11, 16], true) || strlen($piva) !== 11) {
            return ['error' => 'dati_incompleti', 'status' => 422];
        }

        $fornitore = Fornitore::query()->where('piva', $piva)->first();

        if ($fornitore === null) {
            return ['error' => 'agente_sconosciuto', 'status' => 422];
        }

        $existing = $this->find($riferimento);

        if ($existing !== null) {
            if ((int) $existing->fornitori_id !== (int) $fornitore->getKey()) {
                return ['error' => 'cliente_di_altro_agente', 'status' => 409];
            }

            return ['pratica' => $existing, 'created' => false];
        }

        $altro = Pratica::query()
            ->where('codice_fiscale', $fiscale)
            ->whereNotNull('fornitori_id')
            ->where('fornitori_id', '!=', $fornitore->getKey())
            ->exists();

        if ($altro) {
            return ['error' => 'cliente_di_altro_agente', 'status' => 409];
        }

        $praticaDati = (array) ($payload['pratica'] ?? []);
        $etichetta = (string) ($payload['prodotto_etichetta'] ?? $payload['prodotto'] ?? '');
        $tipo = $etichetta === '' ? null : TipoProdotto::query()->where('name', $etichetta)->first();
        $stato = $tipo === null ? null : app(PracticeFinalizer::class)->status($tipo->name, 'PERFEZIONARE');

        $pratica = Pratica::query()->create([
            'codice_pratica' => $this->code($riferimento),
            'denominazione_agente' => $fornitore->name,
            'partita_iva_agente' => $fornitore->piva,
            'denominazione_prodotto' => $etichetta !== '' ? $etichetta : null,
            'cognome_cliente' => $cliente['cognome'] ?? null,
            'nome_cliente' => $cliente['nome'] ?? null,
            'codice_fiscale' => $fiscale,
            'data_inserimento_pratica' => today(),
            'nrate' => $praticaDati['durata_mesi'] ?? null,
            'amount' => $praticaDati['importo_richiesto'] ?? null,
            'is_notowned' => false,
            'allows_direct_requests' => (bool) (($payload['consenso']['contatto_diretto'] ?? false)),
            'upload_at' => now(),
            'company_id' => $fornitore->company_id,
            'pratica_stato_id' => $stato?->getKey(),
            'tipoprodotto_id' => $tipo?->getKey(),
            'fornitori_id' => $fornitore->getKey(),
        ]);

        $this->cliente($pratica, $cliente, $payload);

        return ['pratica' => $pratica, 'created' => true];
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array{document: Document, duplicate: bool}|array{error: string}
     */
    public function receiveFile(Pratica $pratica, string $type, UploadedFile $file, string $sha256, ?string $originId, array $analysis = []): array
    {
        if (! hash_equals(strtolower($sha256), hash_file('sha256', $file->getRealPath()))) {
            return ['error' => 'hash_non_coincide'];
        }

        $documents = $pratica->documents()->get();
        $hash = strtolower($sha256);
        $duplicate = $documents->first(function (Document $document) use ($hash, $originId): bool {
            return $document->file_hash === $hash
                || ($originId !== null && ($document->metadata['id_origine'] ?? null) === $originId);
        });

        if ($duplicate !== null) {
            return ['document' => $duplicate, 'duplicate' => true];
        }

        $document = Document::query()->create([
            'documentable_type' => 'pratica',
            'documentable_id' => (string) $pratica->getKey(),
            'name' => $type !== '' ? $type : $file->getClientOriginalName(),
            'status' => DocumentStatus::UPLOADED,
            'file_hash' => $hash,
            'source_app' => 'unicoagent',
            'company_id' => $pratica->company_id,
            'metadata' => array_filter([
                'tipo' => $type,
                'id_origine' => $originId,
                'analisi' => $analysis,
            ]),
        ]);

        $document->addMedia($file->getRealPath())
            ->usingFileName($file->getClientOriginalName())
            ->preservingOriginal()
            ->toMediaCollection(Document::COLLECTION);

        return ['document' => $document, 'duplicate' => false];
    }

    /** @return list<PdfModule> */
    public function modules(Pratica $pratica): array
    {
        $product = $pratica->tipoprodotto?->name ?? $pratica->denominazione_prodotto;
        $person = strlen((string) $pratica->codice_fiscale) !== 11;

        return PdfModule::query()->active()->get()
            ->filter(fn (PdfModule $module): bool => $module->appliesTo($product, $person))
            ->values()
            ->all();
    }

    public function module(Pratica $pratica, string $id): ?PdfModule
    {
        return collect($this->modules($pratica))->first(fn (PdfModule $module): bool => (string) $module->getKey() === $id);
    }

    /** @return array{id: string}|array{error: string} */
    public function compile(Pratica $pratica, PdfModule $module): array
    {
        try {
            $pdf = app(ModulePrinter::class)->print($pratica, $module);
        } catch (PdfFormException) {
            return ['error' => 'compilazione_non_disponibile'];
        }

        $document = Document::query()->create([
            'documentable_type' => 'pratica',
            'documentable_id' => (string) $pratica->getKey(),
            'document_type_id' => $module->document_type_id,
            'name' => $module->name,
            'status' => DocumentStatus::PRINT,
            'doctype' => 'modulo',
            'source_app' => 'unicoagent',
            'company_id' => $pratica->company_id,
            'metadata' => ['modulo_id' => $module->getKey()],
        ]);

        $document->addMediaFromString($pdf)
            ->usingFileName('modulo-'.$module->getKey().'.pdf')
            ->toMediaCollection(Document::COLLECTION);

        return ['id' => (string) $document->getKey()];
    }

    /** @return array{stato: string}|array{error: string} */
    public function requestSignature(Pratica $pratica, Document $document): array
    {
        $open = $document->latestSignatureRequest();

        if ($open !== null) {
            return ['stato' => $open->status->value];
        }

        $moduleId = $document->metadata['modulo_id'] ?? $document->documentType?->pdfModule?->getKey();
        $module = $moduleId === null ? null : PdfModule::query()->find($moduleId);
        $slots = $module->signature_slots ?? [];

        if ($slots === []) {
            return ['error' => 'firma_non_disponibile'];
        }

        $client = app(RichiedentePratica::class)->ensureClient($pratica);
        $agent = $pratica->fornitori;

        if ($client === null || $agent === null) {
            return ['error' => 'firma_non_disponibile'];
        }

        $signers = [];

        foreach ($slots as $slot) {
            $name = (string) ($slot['slot'] ?? '');
            $role = (string) ($slot['role'] ?? 'client');
            $signers[] = $role === 'fornitore' || $role === 'collaborator'
                ? SignerInput::fromAgent($agent, $name)
                : SignerInput::fromClient($client, $name);
        }

        try {
            $request = app(SignatureRequestService::class)->send($document, $signers);
        } catch (SignatureRequestException) {
            return ['error' => 'firma_non_disponibile'];
        }

        return ['stato' => $request->status->value];
    }

    /**
     * @param  array<string, mixed>  $cliente
     * @param  array<string, mixed>  $payload
     */
    private function cliente(Pratica $pratica, array $cliente, array $payload): void
    {
        $record = app(RichiedentePratica::class)->ensureClient($pratica);

        if ($record === null || ! $record->wasRecentlyCreated) {
            return;
        }

        $consenso = (array) ($payload['consenso'] ?? []);
        $record->fill(array_filter([
            'email' => $cliente['email'] ?? null,
            'phone' => $cliente['telefono'] ?? null,
            'privacy_policy_read_at' => $consenso['privacy_verificata_il'] ?? null,
        ]))->save();
    }
}

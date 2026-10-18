<?php

namespace App\Services\Agenti;

use App\Models\Pratica;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Unico\Core\Documents\Listeners\AnalyzeUploadedDocument;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Client;
use Unico\Core\Models\Document;
use Unico\Core\Models\PdfModule;
use Unico\Core\Models\SignatureRequest;
use Unico\Core\Pdf\ModulePrinter;
use Unico\Core\Pdf\ModuleSuggester;
use Unico\Core\Pdf\PdfFormException;
use Unico\Core\Pdf\PracticeModuleData;
use Unico\Core\Signature\Exceptions\SignatureRequestException;
use Unico\Core\Signature\SignatureRequestService;
use Unico\Core\Signature\SignerDefaults;
use Unico\Core\Signature\SignerInput;

/**
 * Funzioni documentali messe a disposizione delle app esterne (unicoagent, prima su unicoloan) per le pratiche che hanno
 * consegnato: moduli pertinenti, template, modulo compilato con i dati della pratica e firma elettronica con OTP. Sono le
 * funzioni del core usate dal pannello (ModuleSuggester, ModulePrinter, SignatureRequestService): qui c'è solo il filtro
 * sulla pratica e sui dati.
 */
class AgentDocumentFunctions
{
    public function __construct(
        private AgentRequestIntake $intake,
        private ModuleSuggester $suggester,
        private PracticeModuleData $data,
        private ModulePrinter $printer,
        private SignatureRequestService $signatures,
    ) {}

    /** @return Collection<int, PdfModule> moduli attivi pertinenti per prodotto e tipo di cliente della pratica */
    public function modules(Pratica $pratica): Collection
    {
        return $this->suggester->suggest($pratica->tipoprodotto?->name, (bool) $this->client($pratica)->is_person);
    }

    /** @throws AgentIntakeException */
    public function module(Pratica $pratica, int $id): PdfModule
    {
        return $this->modules($pratica)->firstWhere('id', $id)
            ?? throw new AgentIntakeException('modulo_non_trovato', 'Modulo non disponibile per questa pratica.', 404);
    }

    /** @return array<int, string> chiavi dati obbligatorie del modulo che la pratica non ha */
    public function missing(Pratica $pratica, PdfModule $module): array
    {
        $required = $module->loadMissing('pdfModuleFields')->pdfModuleFields->filter->is_required;

        return $this->data->for($pratica)->missingAmong($required->flatMap->referencedKeys());
    }

    /** Contenuto del modulo vuoto, così come è nello storage. @throws AgentIntakeException */
    public function template(PdfModule $module): string
    {
        $disk = Storage::disk(config('unico-core.pdf.modules_disk', 'public'));

        return filled($module->file_path) && $disk->exists($module->file_path)
            ? (string) $disk->get($module->file_path)
            : throw new AgentIntakeException('template_non_disponibile', 'Il file del modulo non è disponibile.', 404);
    }

    /**
     * Compila il modulo con i dati della pratica e lo archivia come documento della pratica (un nuovo documento a ogni
     * chiamata), pronto per l'invio in firma.
     *
     * @throws AgentIntakeException
     */
    public function fill(Pratica $pratica, PdfModule $module): Document
    {
        try {
            $content = $this->printer->print($pratica, $module);
        } catch (PdfFormException) {
            throw new AgentIntakeException('compilazione_non_riuscita', 'Il modulo non è stato compilato.', 422);
        }

        $reference = $pratica->codice_pratica ?: (string) $pratica->getKey();

        /** @var Document $document */
        $document = $pratica->documents()->create([
            'company_id' => $pratica->company_id,
            'document_type_id' => $module->document_type_id,
            'name' => $module->name.' - '.$reference,
            'status' => DocumentStatus::TO_SEND_OTP,
        ]);

        AnalyzeUploadedDocument::suspendedWhile(function () use ($document, $content, $module, $reference): void {
            $document->addMediaFromString($content)
                ->usingFileName(Str::slug($module->name.' '.$reference).'.pdf')
                ->toMediaCollection(Document::COLLECTION);
        });

        return $document->fresh('documentType');
    }

    /** Documento della richiesta (pratica o cliente) con quell'id. @throws AgentIntakeException */
    public function document(Pratica $pratica, string $id): Document
    {
        return $this->intake->documents($pratica)->first(fn (Document $d): bool => (string) $d->getKey() === $id)
            ?? throw new AgentIntakeException('documento_non_trovato', 'Documento non trovato.', 404);
    }

    /** Contenuto del file del documento (il firmato, se c'è), o null se non ancora caricato. */
    public function content(Document $document): ?string
    {
        $media = $document->currentMedia();

        return $media === null ? null : (string) stream_get_contents($media->stream());
    }

    public function lastSignature(Document $document): ?SignatureRequest
    {
        return SignatureRequest::query()->where('document_id', $document->getKey())->latest('id')->first();
    }

    /**
     * Chiede la firma con OTP: i firmatari sono quelli proposti dal pannello (cliente, agente della pratica); l'app può
     * indicare telefono ed email da usare per ogni riquadro di firma.
     *
     * @param  array<string, array{telefono?: ?string, email?: ?string}>  $contacts  per slot (es. «cliente»)
     *
     * @throws AgentIntakeException
     */
    public function requestSignature(Pratica $pratica, Document $document, array $contacts = []): SignatureRequest
    {
        $defaults = SignerDefaults::for($document);
        $signers = [];

        foreach ($document->documentType?->pdfModule?->signature_slots ?? [] as $slot) {
            $name = $slot['slot'];
            $base = $defaults[$name] ?? null;

            if ($base === null) {
                throw new AgentIntakeException('firmatario_mancante', "Firmatario non determinabile per il riquadro «{$name}».", 422);
            }

            $signers[] = new SignerInput(
                $name, $base->role, $base->firstName, $base->lastName,
                $contacts[$name]['email'] ?? $base->email, $contacts[$name]['telefono'] ?? $base->phone,
                $base->taxCode, $base->signerType, $base->signerRef,
            );
        }

        try {
            return $this->signatures->send($document, $signers, null);
        } catch (SignatureRequestException $e) {
            throw new AgentIntakeException('firma_non_richiesta', $e->getMessage(), 422);
        }
    }

    private function client(Pratica $pratica): Client
    {
        return $this->data->findClient($pratica)
            ?? throw new AgentIntakeException('cliente_mancante', 'Cliente della pratica non trovato.', 422);
    }
}

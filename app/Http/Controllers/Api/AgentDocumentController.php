<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pratica;
use App\Services\Agenti\AgentDocumentFunctions;
use App\Services\Agenti\AgentIntakeException;
use App\Services\Agenti\AgentRequestIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Unico\Core\Enums\SignatureRequestStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\PdfModule;
use Unico\Core\Models\SignatureRequest;

/**
 * Funzioni documentali per le app esterne (prima su unicoloan): moduli, template, modulo compilato, file dei documenti
 * e firma OTP, sempre riferite a una richiesta già consegnata. Autenticata da AuthenticateApiClient.
 */
class AgentDocumentController extends Controller
{
    public function __construct(private AgentRequestIntake $intake, private AgentDocumentFunctions $functions) {}

    public function modules(string $riferimento): JsonResponse
    {
        return $this->with($riferimento, fn (Pratica $pratica) => response()->json(['moduli' => $this->functions->modules($pratica)
            ->map(fn (PdfModule $m): array => [
                'id' => $m->getKey(),
                'nome' => $m->name,
                'versione' => $m->version,
                'tipo_documento' => $m->documentType?->slug ?: $m->documentType?->code,
                'firma' => collect($m->signature_slots ?? [])->pluck('slot')->values()->all(),
                'dati_mancanti' => $this->functions->missing($pratica, $m),
            ])->all()]));
    }

    public function template(string $riferimento, int $modulo): Response|JsonResponse
    {
        return $this->with($riferimento, function (Pratica $pratica) use ($modulo) {
            $module = $this->functions->module($pratica, $modulo);

            return $this->pdf($this->functions->template($module), $module->name.'-vuoto');
        });
    }

    public function fill(string $riferimento, int $modulo): JsonResponse
    {
        return $this->with($riferimento, function (Pratica $pratica) use ($modulo) {
            $document = $this->functions->fill($pratica, $this->functions->module($pratica, $modulo));

            return response()->json($this->document($document), 201);
        });
    }

    public function file(string $riferimento, string $documento): Response|JsonResponse
    {
        return $this->with($riferimento, function (Pratica $pratica) use ($documento) {
            $document = $this->functions->document($pratica, $documento);
            $content = $this->functions->content($document);

            if ($content === null) {
                return response()->json(['message' => 'Il documento non ha ancora un file.', 'codice' => 'file_mancante'], 404);
            }

            // Il file con il suo tipo e nome reali: i documenti caricati possono essere anche immagini.
            $media = $document->currentMedia();

            return response($content, 200, [
                'Content-Type' => $media?->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="'.($media?->file_name ?: 'documento').'"',
                'X-Content-Sha256' => hash('sha256', $content),
            ]);
        });
    }

    public function requestSignature(Request $request, string $riferimento, string $documento): JsonResponse
    {
        $data = $request->validate([
            'firmatari' => ['nullable', 'array'],
            'firmatari.*.telefono' => ['nullable', 'string', 'max:40'],
            'firmatari.*.email' => ['nullable', 'email', 'max:191'],
        ]);

        return $this->with($riferimento, function (Pratica $pratica) use ($documento, $data) {
            $signature = $this->functions->requestSignature($pratica, $this->functions->document($pratica, $documento), $data['firmatari'] ?? []);

            return response()->json($this->signature($signature), 201);
        });
    }

    public function signature(string|SignatureRequest $riferimento, ?string $documento = null): JsonResponse|array
    {
        if ($riferimento instanceof SignatureRequest) {
            return [
                'id' => $riferimento->getKey(),
                'documento_id' => $riferimento->document_id,
                'stato' => $riferimento->status->value,
                'firmata' => $riferimento->status === SignatureRequestStatus::Signed,
                'inviata_il' => $riferimento->sent_at?->toIso8601String(),
                'firmata_il' => $riferimento->signed_at?->toIso8601String(),
                'scade_il' => $riferimento->expires_at?->toIso8601String(),
                'firmatari' => $riferimento->signatureRequestSigners->map(fn ($s): array => ['riquadro' => $s->slot, 'stato' => $s->status->value])->all(),
            ];
        }

        return $this->with($riferimento, function (Pratica $pratica) use ($documento) {
            $signature = $this->functions->lastSignature($this->functions->document($pratica, (string) $documento));

            return $signature === null
                ? response()->json(['message' => 'Nessuna richiesta di firma per il documento.', 'codice' => 'firma_assente'], 404)
                : response()->json($this->signature($signature));
        });
    }

    private function with(string $riferimento, \Closure $action): Response|JsonResponse
    {
        $pratica = $this->intake->find($riferimento);

        if ($pratica === null) {
            return response()->json(['message' => 'Richiesta non trovata.'], 404);
        }

        try {
            return $action($pratica);
        } catch (AgentIntakeException $e) {
            return response()->json(['message' => $e->getMessage(), 'codice' => $e->errorCode], $e->status);
        }
    }

    private function pdf(string $content, string $name): Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.(str($name)->slug()->toString() ?: 'documento').'.pdf"',
            'X-Content-Sha256' => hash('sha256', $content),
        ]);
    }

    private function document(Document $document): array
    {
        return [
            'id' => $document->getKey(),
            'tipo' => $document->documentType?->slug ?: $document->documentType?->code,
            'nome' => $document->name,
            'ricevuto' => $document->hasMedia(Document::COLLECTION),
        ];
    }
}

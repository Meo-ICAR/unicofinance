<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Agenti\AgentRequestIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Unico\Core\Models\Document;

class AgentRequestController extends Controller
{
    public function __construct(private AgentRequestIntake $intake) {}

    public function store(Request $request): JsonResponse
    {
        $result = $this->intake->receive($request->json()->all());

        if (isset($result['error'])) {
            return $this->error($result['error'], $result['status']);
        }

        return response()->json(['codice_pratica' => $result['pratica']->codice_pratica], $result['created'] ? 201 : 200);
    }

    public function storeDocument(Request $request, string $riferimento): JsonResponse
    {
        $pratica = $this->intake->find($riferimento);

        if ($pratica === null) {
            return $this->error('pratica_non_trovata', 404);
        }

        $sha256 = (string) $request->header('X-Content-Sha256');

        if (! preg_match('/^[a-f0-9]{64}$/i', $sha256)) {
            return $this->error('hash_mancante', 422, 'Manca l\'intestazione X-Content-Sha256 con l\'hash del file.');
        }

        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            return $this->error('file_mancante', 422);
        }

        $analysis = json_decode((string) $request->input('analisi', ''), true);

        $result = $this->intake->receiveFile(
            $pratica,
            (string) $request->input('tipo', ''),
            $file,
            $sha256,
            filled($request->input('id_origine')) ? (string) $request->input('id_origine') : null,
            is_array($analysis) ? $analysis : [],
        );

        if (isset($result['error'])) {
            return $this->error($result['error'], 422);
        }

        return response()->json(['id' => $result['document']->getKey()], $result['duplicate'] ? 200 : 201);
    }

    public function modules(string $riferimento): JsonResponse
    {
        $pratica = $this->intake->find($riferimento);

        if ($pratica === null) {
            return $this->error('pratica_non_trovata', 404);
        }

        return response()->json([
            'moduli' => collect($this->intake->modules($pratica))->map(fn ($module): array => [
                'id' => $module->getKey(),
                'nome' => $module->name,
            ])->all(),
        ]);
    }

    public function template(string $riferimento, string $modulo): Response
    {
        $pratica = $this->intake->find($riferimento);
        $module = $pratica === null ? null : $this->intake->module($pratica, $modulo);

        if ($pratica === null || $module === null) {
            return $this->error('modulo_non_trovato', 404);
        }

        $path = storage_path('app/public/'.$module->file_path);

        if (! is_file($path)) {
            return $this->error('template_non_disponibile', 404);
        }

        $contents = (string) file_get_contents($path);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'X-Content-Sha256' => hash('sha256', $contents),
        ]);
    }

    public function compile(string $riferimento, string $modulo): JsonResponse
    {
        $pratica = $this->intake->find($riferimento);
        $module = $pratica === null ? null : $this->intake->module($pratica, $modulo);

        if ($pratica === null || $module === null) {
            return $this->error('modulo_non_trovato', 404);
        }

        $result = $this->intake->compile($pratica, $module);

        return isset($result['error'])
            ? $this->error($result['error'], 422)
            : response()->json($result, 201);
    }

    public function sign(string $riferimento, string $documento): JsonResponse
    {
        $document = $this->document($riferimento, $documento);

        if ($document instanceof JsonResponse) {
            return $document;
        }

        $result = $this->intake->requestSignature($document['pratica'], $document['document']);

        return isset($result['error'])
            ? $this->error($result['error'], 422)
            : response()->json($result);
    }

    public function signature(string $riferimento, string $documento): JsonResponse
    {
        $document = $this->document($riferimento, $documento);

        if ($document instanceof JsonResponse) {
            return $document;
        }

        $request = $document['document']->latestSignatureRequest();

        return $request === null
            ? $this->error('firma_non_trovata', 404)
            : response()->json(['stato' => $request->status->value]);
    }

    public function file(string $riferimento, string $documento): Response
    {
        $document = $this->document($riferimento, $documento);

        if ($document instanceof JsonResponse) {
            return $document;
        }

        $media = $document['document']->currentMedia();

        if ($media === null) {
            return $this->error('file_non_disponibile', 404);
        }

        $contents = (string) file_get_contents($media->getPath());

        return response($contents, 200, [
            'Content-Type' => $media->mime_type ?: 'application/pdf',
            'X-Content-Sha256' => hash('sha256', $contents),
        ]);
    }

    /** @return array{pratica: \App\Models\Pratica, document: Document}|JsonResponse */
    private function document(string $riferimento, string $documento): array|JsonResponse
    {
        $pratica = $this->intake->find($riferimento);
        $document = $pratica?->documents()->whereKey($documento)->first();

        if ($pratica === null || $document === null) {
            return $this->error('documento_non_trovato', 404);
        }

        return ['pratica' => $pratica, 'document' => $document];
    }

    private function error(string $code, int $status, ?string $message = null): JsonResponse
    {
        return response()->json([
            'message' => $message ?? match ($code) {
                'dati_incompleti' => 'Mancano riferimento, codice fiscale o partita IVA dell\'agente.',
                'agente_sconosciuto' => 'Nessun agente con questa partita IVA.',
                'cliente_di_altro_agente' => 'Il cliente è già seguito da un altro agente.',
                'pratica_non_trovata' => 'Pratica non trovata.',
                'hash_non_coincide' => 'L\'hash del file non coincide con X-Content-Sha256.',
                'file_mancante' => 'Manca il file.',
                'modulo_non_trovato' => 'Modulo non trovato.',
                'template_non_disponibile' => 'Il PDF del modulo non è disponibile.',
                'compilazione_non_disponibile' => 'Il modulo non si può compilare.',
                'firma_non_disponibile' => 'La firma non è disponibile per questo documento.',
                'firma_non_trovata' => 'Nessuna richiesta di firma.',
                'documento_non_trovato' => 'Documento non trovato.',
                'file_non_disponibile' => 'File non disponibile.',
                default => 'Richiesta rifiutata.',
            },
            'codice' => $code,
        ], $status);
    }
}

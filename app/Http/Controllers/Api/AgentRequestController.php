<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pratica;
use App\Services\Agenti\AgentIntakeException;
use App\Services\Agenti\AgentRequestIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentRequirement;
use Unico\Core\Models\DocumentType;

/**
 * API in ingresso per le app che consegnano richieste di finanziamento (unicoagent, prima su unicoloan): cliente, pratica e
 * documenti. Autenticata da AuthenticateApiClient. Nelle risposte nessun dato personale oltre a quello che l'app ha appena inviato.
 */
class AgentRequestController extends Controller
{
    public function __construct(private AgentRequestIntake $intake) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'riferimento' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/'],
            'prodotto' => ['nullable', 'string', 'max:60'],
            'prodotto_etichetta' => ['nullable', 'string', 'max:120'],
            'risposte' => ['nullable', 'array'],
            'cliente.cognome' => ['required', 'string', 'max:191'],
            'cliente.codice_fiscale' => ['required', 'string', 'regex:/^[A-Za-z0-9]{11,16}$/'],
            'agente.partita_iva' => ['required', 'string', 'max:20'],
            'agente.nome' => ['nullable', 'string', 'max:191'],
            'agente.sigla' => ['nullable', 'string', 'max:20'],
            'agente.whatsapp' => ['nullable', 'string', 'max:40'],
            'documenti' => ['nullable', 'array', 'max:100'],
            'documenti.*.codice' => ['nullable', 'string', 'max:100'],
            'documenti.*.nome' => ['nullable', 'string', 'max:191'],
            'documenti.*.obbligo' => ['nullable', 'string', 'max:40'],
            'documenti.*.stato' => ['nullable', 'string', 'max:40'],
            ...$this->detailRules(),
        ]);

        try {
            $result = $this->intake->intake($data);
        } catch (AgentIntakeException $e) {
            return $this->error($e);
        }

        return response()->json($this->resource($result['pratica'], $result['client']->getKey(), $result['created']), $result['created'] ? 201 : 200);
    }

    /**
     * Aggiornamento dall'agente (dati corretti in conversazione, informativa verificata o rifiutata). Tutto facoltativo: si
     * cambia solo ciò che arriva; `consenso.privacy_verificata_il` a null revoca il consenso.
     */
    public function update(Request $request, string $riferimento): JsonResponse
    {
        $pratica = $this->intake->find($riferimento);

        if ($pratica === null) {
            return response()->json(['message' => 'Richiesta non trovata.'], 404);
        }

        $data = $request->validate([
            'cliente.cognome' => ['sometimes', 'required', 'string', 'max:191'],
            ...$this->detailRules(),
        ]);

        try {
            $changed = $this->intake->update($pratica, $data);
        } catch (AgentIntakeException $e) {
            return $this->error($e);
        }

        return response()->json($this->resource($pratica->fresh()) + ['campi_aggiornati' => $changed]);
    }

    /** Nota per l'istruttore: compare sulla pratica, nella scheda «Da unicoagent». */
    public function note(Request $request, string $riferimento): JsonResponse
    {
        $pratica = $this->intake->find($riferimento);

        if ($pratica === null) {
            return response()->json(['message' => 'Richiesta non trovata.'], 404);
        }

        $data = $request->validate([
            'testo' => ['required', 'string', 'max:5000'],
            'autore' => ['required', 'in:agente,cliente,assistente,istruttore'],
            'inviata_il' => ['nullable', 'date'],
            'id_origine' => ['nullable', 'string', 'max:100'],
        ]);

        $result = $this->intake->addNote($pratica, $data);

        return response()->json([
            'id' => $result['nota']->getKey(),
            'duplicato' => $result['duplicate'],
        ], $result['duplicate'] ? 200 : 201);
    }

    public function show(string $riferimento): JsonResponse
    {
        $pratica = $this->intake->find($riferimento);

        return $pratica === null
            ? response()->json(['message' => 'Richiesta non trovata.'], 404)
            : response()->json($this->resource($pratica));
    }

    public function upload(Request $request, string $riferimento): JsonResponse
    {
        $pratica = $this->intake->find($riferimento);

        if ($pratica === null) {
            return response()->json(['message' => 'Richiesta non trovata.'], 404);
        }

        $request->validate([
            'tipo' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'max:'.config('agent_api.max_file_kb'), 'mimes:'.implode(',', config('agent_api.mimes'))],
            'id_origine' => ['nullable', 'string', 'max:100'],
            'analisi' => ['nullable', 'json'],
        ]);

        $sha256 = (string) $request->header('X-Content-Sha256');

        if (! preg_match('/^[a-f0-9]{64}$/i', $sha256)) {
            return response()->json(['message' => 'Manca l\'intestazione X-Content-Sha256 con l\'hash del file.', 'codice' => 'hash_mancante'], 422);
        }

        try {
            $result = $this->intake->receiveFile(
                $pratica,
                (string) $request->input('tipo'),
                $request->file('file'),
                $sha256,
                $request->input('id_origine'),
                (array) json_decode((string) $request->input('analisi', '[]'), true),
            );
        } catch (AgentIntakeException $e) {
            return $this->error($e);
        }

        return response()->json($this->document($result['document']) + ['duplicato' => $result['duplicate']], $result['duplicate'] ? 200 : 201);
    }

    /** Tipi documento che le richieste possono usare: quelli richiesti dai plichi, con il codice da usare in «tipo». */
    public function documentTypes(): JsonResponse
    {
        $ids = DocumentRequirement::query()->whereNotNull('task_id')->pluck('document_type_id')->unique();
        $mapped = collect(config('agent_api.document_map'))->groupBy(fn ($slug) => $slug, preserveKeys: true)->map(fn ($codes) => $codes->keys()->all());

        return response()->json(['tipi' => DocumentType::query()->whereIn('id', $ids)->orderBy('name')->get()
            ->map(fn (DocumentType $t): array => [
                'tipo' => $t->slug ?: $t->code,
                'nome' => $t->name,
                'alias' => collect($mapped->get($t->slug, []))->merge($mapped->get($t->code, []))->unique()->values()->all(),
            ])->all()]);
    }

    /**
     * Regole comuni a consegna e aggiornamento: dati del cliente oltre a cognome e codice fiscale, pratica, consensi.
     *
     * @return array<string, list<string>>
     */
    private function detailRules(): array
    {
        return [
            'cliente.nome' => ['nullable', 'string', 'max:191'],
            'cliente.email' => ['nullable', 'email', 'max:191'],
            'cliente.telefono' => ['nullable', 'string', 'max:40'],
            'cliente.data_nascita' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'cliente.luogo_nascita' => ['nullable', 'string', 'max:191'],
            'cliente.residenza' => ['nullable', 'array'],
            'cliente.residenza.indirizzo' => ['nullable', 'string', 'max:191'],
            'cliente.residenza.citta' => ['nullable', 'string', 'max:191'],
            'cliente.residenza.provincia' => ['nullable', 'string', 'max:4'],
            'pratica' => ['nullable', 'array'],
            'pratica.importo_richiesto' => ['nullable', 'numeric', 'min:0'],
            'pratica.importo_fascia' => ['nullable', 'string', 'max:60'],
            'pratica.durata_mesi' => ['nullable', 'integer', 'min:1', 'max:600'],
            'consenso' => ['nullable', 'array'],
            'consenso.privacy_ricevuta_il' => ['nullable', 'date'],
            'consenso.privacy_verificata_il' => ['nullable', 'date'],
            'consenso.contatto_diretto' => ['nullable', 'boolean'],
        ];
    }

    private function resource(Pratica $pratica, mixed $clientId = null, bool $created = false): array
    {
        $documents = $this->intake->documents($pratica);

        return [
            'riferimento' => AgentRequestIntake::riferimentoOf($pratica),
            'codice_pratica' => $pratica->codice_pratica,
            'pratica_id' => $pratica->getKey(),
            'cliente_id' => $clientId,
            'stato_pratica' => $pratica->praticaStato?->name,
            'creata' => $created,
            'documenti' => $documents->map(fn (Document $d): array => $this->document($d))->all(),
        ];
    }

    private function document(Document $document): array
    {
        $status = $document->status instanceof DocumentStatus ? $document->status : DocumentStatus::tryFrom((string) $document->status);

        return [
            'id' => $document->getKey(),
            'tipo' => $document->documentType?->slug ?: $document->documentType?->code,
            'nome' => $document->name,
            'stato' => $status?->value,
            'ricevuto' => $document->hasMedia(Document::COLLECTION),
            'motivo_rifiuto' => $status === DocumentStatus::REJECTED ? $document->rejection_note : null,
        ];
    }

    private function error(AgentIntakeException $e): JsonResponse
    {
        return response()->json(['message' => $e->getMessage(), 'codice' => $e->errorCode], $e->status);
    }
}

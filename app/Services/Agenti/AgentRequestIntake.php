<?php

namespace App\Services\Agenti;

use App\Models\ApiClient;
use App\Models\Client;
use App\Models\Pratica;
use App\Services\CambioStatoPratica;
use App\Services\Preventivazione\PracticeFinalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Unico\Core\Documents\PracticeDocumentIntake;
use Unico\Core\Documents\PracticeTaskDocuments;
use Unico\Core\Models\ActivityLog;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\PraticaClient;
use Unico\Core\Models\TipoProdotto;
use Unico\Core\Models\TipoProdottoSub;

/**
 * Accoglie una richiesta di finanziamento consegnata da un'app esterna (unicoagent, che prima consegnava a unicoloan): trova o
 * crea il cliente, apre la pratica dell'agente nello stato da perfezionare con i documenti della check list del prodotto e
 * riceve i file. Il riferimento della richiesta rende l'invio idempotente: ripeterlo non duplica nulla.
 */
class AgentRequestIntake
{
    public const ORIGINE = 'unicoagent';

    public function __construct(
        private ClientForPraticaCreator $clients,
        private PracticeFinalizer $finalizer,
        private PracticeTaskDocuments $checklist,
        private PracticeDocumentIntake $documentIntake,
        private CambioStatoPratica $stati,
        private AgentRequestDetails $details,
    ) {}

    /**
     * Prefisso delle pratiche dell'app che chiama: ogni app crea e raggiunge solo le proprie. Fuori da una richiesta API vale
     * quello di unicoagent.
     */
    public static function prefix(): string
    {
        $client = request()?->attributes->get('api_client');

        return $client instanceof ApiClient && filled($client->pratica_prefix) ? $client->pratica_prefix : (string) config('agent_api.pratica_prefix');
    }

    public static function codiceFor(string $riferimento): string
    {
        return self::prefix().$riferimento;
    }

    public static function riferimentoOf(Pratica $pratica): string
    {
        return Str::after((string) $pratica->codice_pratica, self::prefix());
    }

    public function find(string $riferimento): ?Pratica
    {
        return Pratica::query()->where('codice_pratica', self::codiceFor($riferimento))->first();
    }

    /**
     * @param  array<string,mixed>  $data  richiesta già validata
     * @return array{pratica: Pratica, client: Client, created: bool}
     *
     * @throws AgentIntakeException
     */
    public function intake(array $data): array
    {
        // Due invii contemporanei dello stesso riferimento non creano due pratiche: il secondo aspetta e trova la prima.
        return Cache::lock('agent-intake:'.self::codiceFor($data['riferimento']), 30)
            ->block(10, fn (): array => $this->intakeUnlocked($data));
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array{pratica: Pratica, client: Client, created: bool}
     *
     * @throws AgentIntakeException
     */
    private function intakeUnlocked(array $data): array
    {
        $existing = $this->find($data['riferimento']);

        if ($existing !== null) {
            // Lo stesso riferimento vale solo per la stessa richiesta: cliente e agente devono coincidere.
            if (Str::upper(trim((string) $existing->codice_fiscale)) !== Str::upper(trim((string) $data['cliente']['codice_fiscale']))
                || trim((string) $existing->partita_iva_agente) !== trim((string) $data['agente']['partita_iva'])) {
                throw new AgentIntakeException('riferimento_gia_usato', 'Il riferimento è già stato usato per un\'altra richiesta.', 409);
            }

            return ['pratica' => $existing, 'client' => $this->clientOf($existing), 'created' => false];
        }

        $agent = Fornitore::query()
            ->where('piva', trim((string) $data['agente']['partita_iva']))
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('dismissed_at')->orWhere('dismissed_at', '>', today()))
            ->first()
            ?? throw new AgentIntakeException('agente_sconosciuto', 'Nessun agente attivo con questa partita IVA.');

        $tipo = $this->tipoProdotto($data)
            ?? throw new AgentIntakeException('prodotto_sconosciuto', 'Prodotto non riconosciuto e nessun tipo prodotto di ripiego.');

        $cliente = $data['cliente'];
        $taxCode = Str::upper(trim($cliente['codice_fiscale']));

        if (! $this->clients->isUsableByAgent($taxCode, $agent)) {
            throw new AgentIntakeException('cliente_di_altro_agente', 'Il cliente risulta già gestito da un altro agente.', 409);
        }

        // Cliente, pratica, storico e documenti richiesti si creano insieme: se un passo fallisce non resta una pratica a metà
        // che un nuovo invio scambierebbe per completa.
        return AgentNotifications::quietly(fn (): array => DB::connection((new Pratica)->getConnectionName())->transaction(fn (): array => $this->create($data, $agent, $tipo, $taxCode)));
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array{pratica: Pratica, client: Client, created: bool}
     */
    private function create(array $data, Fornitore $agent, TipoProdotto $tipo, string $taxCode): array
    {
        $cliente = $data['cliente'];
        $client = $this->clients->findOrCreate($taxCode, (string) $cliente['cognome'], $cliente['nome'] ?? null, (string) ($cliente['email'] ?? ''), (string) ($cliente['telefono'] ?? ''), $agent->company_id);
        $stato = $this->finalizer->status($tipo->name, (string) config('agent_api.stato_iniziale'));

        $pratica = Pratica::query()->create([
            'codice_pratica' => self::codiceFor($data['riferimento']),
            'nome_cliente' => $cliente['nome'] ?? null,
            'cognome_cliente' => $cliente['cognome'],
            'codice_fiscale' => $taxCode,
            'denominazione_prodotto' => $data['prodotto_etichetta'] ?? $tipo->name,
            'amount' => $data['pratica']['importo_richiesto'] ?? null,
            'data_inserimento_pratica' => today(),
            'denominazione_agente' => $agent->name,
            'partita_iva_agente' => $agent->piva,
            'is_notowned' => false,
            'upload_at' => now(),
            'company_id' => $agent->company_id,
            'pratica_stato_id' => $stato->getKey(),
            'tipoprodotto_id' => $tipo->getKey(),
            'fornitori_id' => $agent->getKey(),
        ]);

        PraticaClient::query()->firstOrCreate(
            ['pratica_id' => $pratica->getKey(), 'client_id' => $client->getKey()],
            ['role' => 'richiedente', 'name' => trim($client->first_name.' '.$client->name)],
        );

        $this->stati->registra($pratica, null, $stato->getKey(), 'Richiesta consegnata da unicoagent', self::ORIGINE);

        // Nascita, residenza, consensi e durata: solo dove l'anagrafica è vuota. Il resto della richiesta va nel registro.
        $this->details->fillClient($client, $cliente, overwrite: false);
        $this->details->applyConsent($client, (array) ($data['consenso'] ?? []), revocable: false);
        $this->details->fillPratica($pratica, (array) ($data['pratica'] ?? []), overwrite: false);
        $this->details->log($pratica, 'richiesta', 'Richiesta consegnata da unicoagent', $this->details->requestSummary($data));

        // Documenti richiesti: check list del prodotto per il gruppo lavorativo del cliente e documenti dello stato iniziale.
        $this->checklist->attachChecklist($pratica, $this->gruppoDocumentale($client), $pratica->clienti_id);
        $this->checklist->attachToPractice($pratica);

        return ['pratica' => $pratica, 'client' => $client, 'created' => true];
    }

    /** L'agente può ancora cambiare i dati finché la pratica è nello stato iniziale (da perfezionare). */
    public function isOpenForAgent(Pratica $pratica): bool
    {
        return $pratica->praticaStato?->codice === (string) config('agent_api.stato_iniziale');
    }

    /**
     * Aggiornamento dall'agente: dati del cliente e della pratica solo finché è da perfezionare, i consensi sempre (anche la
     * revoca). I valori inviati sostituiscono quelli presenti.
     *
     * @param  array<string,mixed>  $data  aggiornamento già validato
     * @return list<string> campi cambiati
     *
     * @throws AgentIntakeException
     */
    public function update(Pratica $pratica, array $data): array
    {
        if ((filled($data['cliente'] ?? null) || filled($data['pratica'] ?? null)) && ! $this->isOpenForAgent($pratica)) {
            throw new AgentIntakeException('pratica_in_lavorazione', 'La pratica è già in lavorazione: si possono aggiornare solo i consensi.', 409);
        }

        $client = $this->clientOf($pratica);

        return DB::connection($pratica->getConnectionName())->transaction(function () use ($pratica, $client, $data): array {
            $cliente = (array) ($data['cliente'] ?? []);
            $changed = [
                ...$this->details->fillClient($client, $cliente, overwrite: true),
                ...$this->details->fillPratica($pratica, (array) ($data['pratica'] ?? []), overwrite: true),
                ...$this->details->applyConsent($client, (array) ($data['consenso'] ?? []), revocable: true),
            ];

            // Nome e cognome sono copiati anche sulla pratica.
            $pratica->forceFill(array_filter([
                'cognome_cliente' => in_array('name', $changed, true) ? $client->name : null,
                'nome_cliente' => in_array('first_name', $changed, true) ? $client->first_name : null,
            ]))->save();

            if ($changed !== []) {
                $this->details->log($pratica, 'aggiornamento', 'Dati aggiornati da unicoagent', ['campi' => array_values(array_unique($changed))]);
            }

            return array_values(array_unique($changed));
        });
    }

    /**
     * Nota per l'istruttore (un messaggio della conversazione, una richiesta dell'agente). Con `id_origine` è idempotente.
     *
     * @param  array<string,mixed>  $data  nota già validata
     * @return array{nota: ActivityLog, duplicate: bool}
     */
    public function addNote(Pratica $pratica, array $data): array
    {
        $originId = $data['id_origine'] ?? null;
        $existing = $originId === null ? null : $this->details->findNote($pratica, $originId);

        if ($existing !== null) {
            return ['nota' => $existing, 'duplicate' => true];
        }

        return ['nota' => $this->details->log($pratica, 'nota', (string) $data['testo'], [
            'autore' => $data['autore'],
            'inviata_il' => $data['inviata_il'] ?? null,
            'id_origine' => $originId,
        ]), 'duplicate' => false];
    }

    /**
     * Tipo prodotto del core per la richiesta: la mappa configurata, poi l'etichetta e il codice del prodotto se coincidono
     * con il nome di un tipo, altrimenti il tipo di ripiego (`agent_api.default_tipo_prodotto`).
     *
     * @param  array<string,mixed>  $data
     */
    private function tipoProdotto(array $data): ?TipoProdotto
    {
        $candidates = array_filter([
            config('agent_api.product_map.'.($data['prodotto'] ?? '')),
            $data['prodotto_etichetta'] ?? null,
            $data['prodotto'] ?? null,
            config('agent_api.default_tipo_prodotto'),
        ]);

        foreach ($candidates as $candidate) {
            $found = TipoProdotto::query()->whereRaw('lower(name) = ?', [mb_strtolower((string) $candidate)])->first();

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /** Gruppo documentale della tipologia lavorativa del cliente, come in CompletezzaDocumentale; nullo se non è indicata. */
    private function gruppoDocumentale(Client $client): ?string
    {
        if (blank($client->employment_typology)) {
            return null;
        }

        $gruppo = TipoProdottoSub::query()->where('name', $client->employment_typology)->value('employment_document_group');

        return $gruppo instanceof \BackedEnum ? $gruppo->value : ($gruppo === null ? null : (string) $gruppo);
    }

    /** Tutti i documenti della richiesta: quelli della pratica e quelli del cliente. @return Collection<int, Document> */
    public function documents(Pratica $pratica): Collection
    {
        return $pratica->documents()->with('documentType')->get()
            ->merge($this->clientOf($pratica)->documents()->with('documentType')->get())
            ->values();
    }

    /**
     * Riceve un file: lo mette sul documento richiesto di quel tipo (o su uno nuovo) con i controlli automatici del core.
     *
     * @param  array<string,mixed>  $analysis  esito dell'analisi già fatta dall'app chiamante, conservato nei metadati
     * @return array{document: Document, duplicate: bool}
     *
     * @throws AgentIntakeException
     */
    public function receiveFile(Pratica $pratica, string $type, UploadedFile $file, string $sha256, ?string $originId, array $analysis = []): array
    {
        $sha256 = strtolower($sha256);

        if (! hash_equals($sha256, hash_file('sha256', $file->getRealPath()))) {
            throw new AgentIntakeException('hash_non_corrispondente', 'Il file ricevuto non corrisponde all\'hash dichiarato.');
        }

        $documentType = $this->resolveType($type);
        $duplicate = $this->documents($pratica)
            ->where('document_type_id', $documentType->getKey())
            ->first(fn (Document $d): bool => $d->file_hash === $sha256 || $d->channel_ref !== null && $d->channel_ref === $originId && $d->getFirstMedia(Document::COLLECTION) !== null);

        if ($duplicate !== null) {
            return ['document' => $duplicate, 'duplicate' => true];
        }

        $document = $this->documentIntake->storeTyped($pratica, $file, $documentType->getKey(), collect([$documentType]));

        $document->forceFill([
            'channel' => 'whatsapp',
            'channel_ref' => $originId,
            'file_hash' => $document->file_hash ?? $sha256,
            'metadata' => array_merge((array) $document->metadata, ['origine' => array_filter(['app' => self::ORIGINE, 'analisi' => $analysis ?: null])]),
        ])->save();

        return ['document' => $document->fresh('documentType'), 'duplicate' => false];
    }

    /** @throws AgentIntakeException */
    public function resolveType(string $type): DocumentType
    {
        $wanted = (string) config('agent_api.document_map.'.$type, $type);

        return DocumentType::query()->where('slug', $wanted)->orWhere('code', $wanted)->first()
            ?? throw new AgentIntakeException('tipo_documento_sconosciuto', "Tipo documento «{$type}» non riconosciuto.");
    }

    /** @throws AgentIntakeException */
    public function clientOf(Pratica $pratica): Client
    {
        $taxCode = Str::upper(trim((string) $pratica->codice_fiscale));

        return Client::query()->where('tax_code', $taxCode)->orWhere('vat_number', $taxCode)->first()
            ?? throw new AgentIntakeException('cliente_mancante', 'Cliente della pratica non trovato.', 422);
    }
}

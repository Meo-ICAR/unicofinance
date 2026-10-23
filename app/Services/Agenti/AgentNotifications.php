<?php

namespace App\Services\Agenti;

use App\Jobs\SendAgentEvent;
use App\Models\ApiClient;
use App\Models\Pratica;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Client;
use Unico\Core\Models\Document;

/**
 * Avvisa l'app che ha creato la pratica (unicoagent) quando l'istruttoria respinge un documento o ne chiede uno nuovo, così
 * l'agente lo chiede al cliente. Solo per le app con `webhook_url`; nessun dato personale oltre a quello del documento.
 */
class AgentNotifications
{
    /** Stati che chiedono all'agente di rifare il documento. */
    private const DA_RIFARE = [DocumentStatus::REJECTED, DocumentStatus::UNREADABLE, DocumentStatus::EXPIRED, DocumentStatus::ANOMALY, DocumentStatus::MISSING_DATA];

    /** Stati di un documento che manca ancora. */
    private const RICHIESTI = [DocumentStatus::PENDING, DocumentStatus::SUPPLEMENTARY];

    private static bool $muted = false;

    /** Esegue senza avvisi: i documenti creati insieme alla richiesta l'app li conosce già. */
    public static function quietly(callable $callback): mixed
    {
        $previous = self::$muted;
        self::$muted = true;

        try {
            return $callback();
        } finally {
            self::$muted = $previous;
        }
    }

    public function created(Document $document): void
    {
        if (in_array($this->status($document), self::RICHIESTI, true)) {
            $this->notify($document, 'documento.richiesto');
        }
    }

    public function updated(Document $document): void
    {
        if (! $document->wasChanged('status')) {
            return;
        }

        $status = $this->status($document);

        if (in_array($status, self::DA_RIFARE, true)) {
            $this->notify($document, 'documento.da_rifare');
        } elseif ($status === DocumentStatus::SUPPLEMENTARY) {
            $this->notify($document, 'documento.richiesto');
        }
    }

    private function notify(Document $document, string $event): void
    {
        if (self::$muted) {
            return;
        }

        $clients = ApiClient::query()->where('is_active', true)->whereNotNull('webhook_url')->whereNotNull('pratica_prefix')->get();

        if ($clients->isEmpty()) {
            return;
        }

        foreach ($this->pratiche($document) as $pratica) {
            $client = $clients->first(fn (ApiClient $c): bool => str_starts_with((string) $pratica->codice_pratica, (string) $c->pratica_prefix));

            if ($client === null) {
                continue;
            }

            $type = $document->documentType;
            $status = $this->status($document);

            SendAgentEvent::dispatch($client->getKey(), [
                // Un documento del cliente può toccare più pratiche: l'id dell'evento le distingue.
                'event_id' => "{$event}:{$pratica->getKey()}:{$document->getKey()}:{$status?->value}:".($document->updated_at?->timestamp ?? time()),
                'evento' => $event,
                'riferimento' => Str::after((string) $pratica->codice_pratica, (string) $client->pratica_prefix),
                'documento' => array_filter([
                    'id' => $document->getKey(),
                    'tipo' => $type?->slug ?: $type?->code,
                    'alias' => $this->aliases($type?->slug, $type?->code),
                    'nome' => $type?->name ?: $document->name,
                    'stato' => $status?->value,
                    'motivo' => $event === 'documento.da_rifare' ? $document->rejection_note : null,
                ], fn ($value): bool => $value !== null && $value !== []),
                'avvenuto_il' => now()->toIso8601String(),
            ], $client->name)->afterCommit();
        }
    }

    /**
     * Le pratiche toccate: quella del documento, o per un documento del cliente le sue pratiche create via API.
     *
     * @return Collection<int, Pratica>
     */
    private function pratiche(Document $document): Collection
    {
        if ($document->documentable_type === 'pratica') {
            return Pratica::query()->whereKey($document->documentable_id)->get();
        }

        if ($document->documentable_type !== 'client') {
            return collect();
        }

        $client = Client::query()->find($document->documentable_id);
        $codes = array_filter([$client?->tax_code, $client?->vat_number]);

        return $codes === [] ? collect() : Pratica::query()->whereIn('codice_fiscale', $codes)->get();
    }

    /** Codici con cui l'app chiama questo tipo (la mappa `agent_api.document_map` al contrario). @return list<string> */
    private function aliases(?string $slug, ?string $code): array
    {
        return collect(config('agent_api.document_map'))
            ->filter(fn ($target): bool => $target !== null && in_array($target, [$slug, $code], true))
            ->keys()->values()->all();
    }

    private function status(Document $document): ?DocumentStatus
    {
        return $document->status instanceof DocumentStatus ? $document->status : DocumentStatus::tryFrom((string) $document->status);
    }
}

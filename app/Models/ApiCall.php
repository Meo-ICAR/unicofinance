<?php

namespace App\Models;

use App\Enums\ApiCallStatus;
use App\Enums\ApiProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Unico\Core\Concerns\UsesCoreConnection;
use Unico\Core\Models\Client as CoreClient;
use Unico\Core\Models\Document;
use Unico\Core\Models\Pratica as CorePratica;

/**
 * Chiamata a un web service esterno, con esito e costo.
 *
 * @property ApiProvider $provider
 * @property ApiCallStatus $status
 */
class ApiCall extends Model
{
    use UsesCoreConnection;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'provider',
        'operation',
        'subject_type',
        'subject_id',
        'user_id',
        'status',
        'http_status',
        'duration_ms',
        'cost',
        'currency',
        'request',
        'response',
        'summary',
        'reference',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => ApiProvider::class,
            'status' => ApiCallStatus::class,
            'cost' => 'decimal:4',
            'request' => 'array',
            'response' => 'array',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Pratica da cui è partita la chiamata, anche se il soggetto è un suo documento. */
    public function praticaDiRiferimento(): ?Pratica
    {
        $soggetto = $this->soggetto();

        if ($soggetto instanceof CorePratica) {
            return Pratica::query()->find($soggetto->getKey());
        }

        $documento = $this->documento($soggetto);

        if ($documento?->documentable instanceof CorePratica) {
            return Pratica::query()->find($documento->documentable->getKey());
        }

        return null;
    }

    /** Cliente da cui è partita la chiamata, o il richiedente della pratica collegata. */
    public function clienteDiRiferimento(): ?Client
    {
        $soggetto = $this->soggetto();

        if ($soggetto instanceof CoreClient) {
            return Client::query()->find($soggetto->getKey());
        }

        $documento = $this->documento($soggetto);

        if ($documento?->documentable instanceof CoreClient) {
            return Client::query()->find($documento->documentable->getKey());
        }

        $codice = $this->praticaDiRiferimento()?->codice_fiscale;

        return filled($codice) ? Client::query()->where('tax_code', $codice)->first() : null;
    }

    public function etichettaPratica(): ?string
    {
        $pratica = $this->praticaDiRiferimento();

        if ($pratica === null) {
            return null;
        }

        return collect([$pratica->codice_pratica, $pratica->cognome_cliente])->filter()->implode(' · ') ?: null;
    }

    public function etichettaCliente(): ?string
    {
        $cliente = $this->clienteDiRiferimento();

        if ($cliente === null) {
            return null;
        }

        return collect([$cliente->name, $cliente->first_name])->filter()->implode(' ') ?: null;
    }

    /** @param  'asc'|'desc'  $direction */
    public function scopeOrderByPratica(Builder $query, string $direction): Builder
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return $query->orderByRaw(
            "case
                when subject_type in ('pratica', ?, ?) then (select codice_pratica from pratiches where pratiches.id = api_calls.subject_id limit 1)
                when subject_type in ('document', ?) then (
                    select p.codice_pratica from documents d
                    inner join pratiches p on p.id = d.documentable_id
                    where d.id = api_calls.subject_id and d.documentable_type in ('pratica', ?, ?)
                    limit 1
                )
            end {$direction}",
            [Pratica::class, CorePratica::class, Document::class, Pratica::class, CorePratica::class],
        );
    }

    /** @param  'asc'|'desc'  $direction */
    public function scopeOrderByCliente(Builder $query, string $direction): Builder
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';
        $clienti = "'client', ?, ?";

        return $query->orderByRaw(
            "case
                when subject_type in ({$clienti}) then (select name from clients where clients.id = api_calls.subject_id limit 1)
                when subject_type in ('pratica', ?, ?) then (
                    select c.name from pratiches p
                    inner join clients c on c.tax_code = p.codice_fiscale
                    where p.id = api_calls.subject_id limit 1
                )
                when subject_type in ('document', ?) then (
                    select coalesce(
                        (select c.name from clients c where c.id = d.documentable_id and d.documentable_type in ({$clienti}) limit 1),
                        (select c.name from pratiches p inner join clients c on c.tax_code = p.codice_fiscale where p.id = d.documentable_id and d.documentable_type in ('pratica', ?, ?) limit 1)
                    )
                    from documents d where d.id = api_calls.subject_id limit 1
                )
            end {$direction}",
            [Client::class, CoreClient::class, Pratica::class, CorePratica::class, Document::class, Client::class, CoreClient::class, Pratica::class, CorePratica::class],
        );
    }

    private function soggetto(): ?Model
    {
        if (blank($this->subject_type) || blank($this->subject_id)) {
            return null;
        }

        return $this->subject;
    }

    private function documento(?Model $soggetto): ?Document
    {
        if (! $soggetto instanceof Document) {
            return null;
        }

        if (! $soggetto->relationLoaded('documentable')) {
            $soggetto->load('documentable');
        }

        return $soggetto;
    }
}

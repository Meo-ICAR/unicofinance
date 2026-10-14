<?php

namespace App\Services;

use App\Enums\ApiProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AmlScreeningService
{
    public function __construct(private readonly ApiCallLogger $logger) {}

    /**
     * Cerca una persona o un'entita' nelle liste PEP di sanctions.io.
     *
     * @return array<int, array{name: string, score: float, title: ?string, countries: array<int, string>, source: ?string}>
     *
     * @throws RuntimeException
     */
    public function searchPep(string $name, bool $isPerson = true, ?string $dateOfBirth = null, ?Model $subject = null): array
    {
        $apiKey = config('services.aml.api_key');

        if (blank($apiKey)) {
            throw new RuntimeException('Chiave AML non configurata (AML_API_KEY).');
        }

        $query = array_filter([
            'name' => trim($name),
            'data_source' => 'PEP',
            'entity_type' => $isPerson ? 'Individual' : 'Entity',
            'min_score' => config('services.aml.min_score'),
            'date_of_birth' => $dateOfBirth,
        ], fn ($value) => filled($value));

        $startedAt = microtime(true);

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->withHeaders(['Accept' => 'application/json; version=2.3'])
                ->timeout(30)
                ->get(rtrim((string) config('services.aml.base_url'), '/').'/search/', $query);
        } catch (ConnectionException $e) {
            $this->logger->record(ApiProvider::SanctionsIo, 'pep_search', $subject, $query, $startedAt, null, false, error: $e->getMessage());

            throw new RuntimeException('Servizio AML non raggiungibile.', 0, $e);
        }

        if (! $response->successful()) {
            $this->logger->record(ApiProvider::SanctionsIo, 'pep_search', $subject, $query, $startedAt, $response->status(), false, error: mb_substr($response->body(), 0, 500));

            throw new RuntimeException('Errore servizio AML (HTTP '.$response->status().').');
        }

        $matches = collect($response->json('results', []))
            ->map(fn (array $match): array => [
                'name' => (string) ($match['name'] ?? ''),
                'score' => (float) ($match['confidence_score'] ?? 0),
                'title' => $match['title'] ?? null,
                'countries' => array_values(array_map('strval', (array) ($match['citizenship'] ?? $match['program'] ?? []))),
                'source' => $match['data_source']['short_name'] ?? null,
            ])
            ->all();

        $this->logger->record(
            ApiProvider::SanctionsIo,
            'pep_search',
            $subject,
            $query,
            $startedAt,
            $response->status(),
            true,
            summary: $matches === [] ? 'Nessuna corrispondenza' : count($matches).' possibili corrispondenze',
            reference: $response->json('search.id'),
            response: $response->json(),
        );

        return $matches;
    }
}

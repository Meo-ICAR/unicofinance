<?php

namespace App\Services;

use App\Enums\ApiProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CervedService
{
    public function __construct(private readonly ApiCallLogger $logger) {}

    /**
     * Interroga l'API Cerved (score impresa) per codice fiscale / P.IVA.
     *
     * @return array{id_soggetto: mixed, denominazione: ?string, codice_score: ?string, descrizione_score: ?string, valore: mixed, categoria_codice: ?string, categoria_descrizione: ?string}
     *
     * @throws RuntimeException
     */
    public function fetchScore(string $taxCode, ?Model $subject = null): array
    {
        $apiKey = config('services.cerved.api_key');
        $baseUrl = config('services.cerved.url');

        if (blank($apiKey) || blank($baseUrl)) {
            throw new RuntimeException('Credenziali Cerved non configurate (CERVED_API_KEY / CERVED_URL).');
        }

        $startedAt = microtime(true);
        $request = ['codice_fiscale' => trim($taxCode)];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => '*/*',
                'apikey' => $apiKey,
            ])->timeout(30)->get($baseUrl.urlencode(trim($taxCode)));
        } catch (ConnectionException $e) {
            $this->logger->record(ApiProvider::Cerved, 'score', $subject, $request, $startedAt, null, false, error: $e->getMessage());

            throw new RuntimeException('Cerved non raggiungibile.', 0, $e);
        }

        if (! $response->successful()) {
            $this->logger->record(ApiProvider::Cerved, 'score', $subject, $request, $startedAt, $response->status(), false, error: mb_substr($response->body(), 0, 500));

            throw new RuntimeException('Errore Cerved (HTTP '.$response->status().').');
        }

        $data = $response->json();
        $score = $data['scores'][0] ?? null;

        if ($score === null) {
            $this->logger->record(ApiProvider::Cerved, 'score', $subject, $request, $startedAt, $response->status(), true, summary: 'Nessun dato score', response: (array) $data);

            throw new RuntimeException('Nessun dato score trovato per il codice indicato.');
        }

        $this->logger->record(
            ApiProvider::Cerved,
            'score',
            $subject,
            $request,
            $startedAt,
            $response->status(),
            true,
            summary: 'Score '.($score['valore'] ?? 'n.d.').' - '.($score['categoria_descrizione'] ?? 'n.d.'),
            reference: isset($data['id_soggetto']) ? (string) $data['id_soggetto'] : null,
            response: $data,
        );

        return [
            'id_soggetto' => $data['id_soggetto'] ?? null,
            'denominazione' => $data['denominazione'] ?? null,
            'codice_score' => $score['codice_score'] ?? null,
            'descrizione_score' => $score['descrizione_score'] ?? null,
            'valore' => $score['valore'] ?? null,
            'categoria_codice' => $score['categoria_codice'] ?? null,
            'categoria_descrizione' => $score['categoria_descrizione'] ?? null,
        ];
    }
}

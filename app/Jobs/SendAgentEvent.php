<?php

namespace App\Jobs;

use App\Models\ApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Consegna un avviso all'app (POST su `webhook_url`), firmato come le richieste che l'app manda qui: HMAC-SHA256 di
 * «timestamp.POST.percorso.sha256-del-corpo» con il segreto dell'app. Ritenta su rete, 5xx e 408/425/429; gli altri 4xx sono definitivi.
 */
class SendAgentEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600];

    /** @param  array<string, mixed>  $payload */
    public function __construct(public int $apiClientId, public array $payload) {}

    public function handle(): void
    {
        $client = ApiClient::query()->where('is_active', true)->find($this->apiClientId);
        $url = (string) $client?->webhook_url;

        if ($client === null || ! (str_starts_with($url, 'https://') || (str_starts_with($url, 'http://') && app()->environment(['local', 'testing'])))) {
            return;
        }

        $body = (string) json_encode($this->payload);
        $timestamp = (string) time();
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');

        try {
            $response = Http::timeout(10)->acceptJson()
                ->withHeaders(['X-Timestamp' => $timestamp, 'X-Signature' => $client->signatureFor($timestamp, 'POST', $path, hash('sha256', $body))])
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (ConnectionException) {
            throw new RuntimeException('Avviso all\'app non consegnato: servizio non raggiungibile.');
        }

        if ($response->successful()) {
            return;
        }

        // Nessun dato personale nel log: solo evento e codice.
        Log::warning('Avviso all\'app rifiutato', ['app' => $client->name, 'evento' => $this->payload['evento'] ?? null, 'status' => $response->status()]);

        if ($response->serverError() || in_array($response->status(), [408, 425, 429], true)) {
            throw new RuntimeException("Avviso all'app non consegnato (codice {$response->status()}).");
        }
    }
}

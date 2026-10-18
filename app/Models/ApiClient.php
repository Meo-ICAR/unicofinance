<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** App esterna autorizzata a usare le API in ingresso. Token e segreto in chiaro esistono solo al momento della creazione. */
class ApiClient extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'is_active' => 'boolean', 'last_used_at' => 'datetime'];
    }

    /** @return array{client: self, token: string, secret: string} */
    public static function issue(string $name, ?string $praticaPrefix = null, ?string $webhookUrl = null): array
    {
        $praticaPrefix ??= static::defaultPrefix($name);

        // Lettere e cifre e un solo trattino finale: così nessun prefisso è l'inizio di un altro.
        if (preg_match('/^[A-Z0-9]+-$/', $praticaPrefix) !== 1) {
            throw new InvalidArgumentException("Prefisso «{$praticaPrefix}» non valido: lettere maiuscole e cifre seguite da un trattino.");
        }

        // Un indirizzo che SendAgentEvent non userebbe farebbe sparire gli avvisi senza errori: meglio rifiutarlo subito.
        if ($webhookUrl !== null && ! static::isUsableWebhook($webhookUrl)) {
            throw new InvalidArgumentException("Webhook «{$webhookUrl}» non valido: serve un indirizzo https.");
        }

        $token = Str::random(48);
        $secret = Str::random(48);

        $client = static::create(['name' => $name, 'pratica_prefix' => $praticaPrefix, 'webhook_url' => $webhookUrl, 'token_hash' => hash('sha256', $token), 'secret' => $secret]);

        return ['client' => $client, 'token' => $token, 'secret' => $secret];
    }

    /** Solo https; http solo in sviluppo e nei test. */
    public static function isUsableWebhook(string $url): bool
    {
        $scheme = filter_var($url, FILTER_VALIDATE_URL) === false ? null : parse_url($url, PHP_URL_SCHEME);

        return $scheme === 'https' || ($scheme === 'http' && app()->environment(['local', 'testing']));
    }

    /** unicoagent tiene il prefisso storico (`agent_api.pratica_prefix`), le altre app il proprio nome in maiuscolo. */
    public static function defaultPrefix(string $name): string
    {
        return $name === 'unicoagent' ? (string) config('agent_api.pratica_prefix') : Str::upper(Str::slug($name, '')).'-';
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token_hash', hash('sha256', $token))->where('is_active', true)->first();
    }

    /**
     * Firma attesa di una richiesta: HMAC-SHA256 di «timestamp.METODO.percorso.sha256-del-contenuto». Il contenuto è il corpo
     * (JSON) o, nei caricamenti, il file: così la firma lega la richiesta al metodo, all'endpoint e ai dati.
     */
    public function signatureFor(string $timestamp, string $method, string $path, string $contentSha256): string
    {
        return hash_hmac('sha256', implode('.', [$timestamp, strtoupper($method), '/'.ltrim($path, '/'), $contentSha256]), $this->secret);
    }
}

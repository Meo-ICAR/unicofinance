<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
    public static function issue(string $name): array
    {
        $token = Str::random(48);
        $secret = Str::random(48);

        $client = static::create(['name' => $name, 'token_hash' => hash('sha256', $token), 'secret' => $secret]);

        return ['client' => $client, 'token' => $token, 'secret' => $secret];
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

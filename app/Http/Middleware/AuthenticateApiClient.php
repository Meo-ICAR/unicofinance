<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica le app che usano le API in ingresso (unicoagent, che prima consegnava a unicoloan): token Bearer e, salvo diversa configurazione, firma HMAC del corpo con
 * un timestamp recente (X-Timestamp, X-Signature: vedi ApiClient::signatureFor()). Gli errori non dicono quale dei controlli è fallito.
 */
class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = filled($request->bearerToken()) ? ApiClient::findByToken($request->bearerToken()) : null;

        if ($client === null || ! $this->signatureIsValid($request, $client)) {
            return response()->json(['message' => 'Non autorizzato.'], 401);
        }

        $client->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('api_client', $client);

        return $next($request);
    }

    private function signatureIsValid(Request $request, ApiClient $client): bool
    {
        if (! config('agent_api.require_signature')) {
            return true;
        }

        $timestamp = (string) $request->header('X-Timestamp');
        $signature = (string) $request->header('X-Signature');

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > (int) config('agent_api.timestamp_tolerance')) {
            return false;
        }

        // Nei caricamenti (multipart) il contenuto firmato è il file ricevuto, altrimenti il corpo grezzo: l'hash si calcola
        // sempre qui, mai preso dall'intestazione, così il contenuto non si può cambiare dopo la firma.
        $file = $request->file('file');
        $contentHash = $file instanceof UploadedFile
            ? (string) hash_file('sha256', $file->getRealPath())
            : hash('sha256', $request->getContent());

        return $signature !== '' && hash_equals($client->signatureFor($timestamp, $request->method(), $request->getPathInfo(), $contentHash), $signature);
    }
}

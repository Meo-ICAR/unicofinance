<?php

namespace App\Http\Middleware;

use App\Models\AgentApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Token Bearer e firma HMAC della richiesta, con scadenza di cinque minuti. */
class AuthenticateAgentApi
{
    private const SKEW_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        $client = $token === '' ? null : AgentApiClient::query()->where('token_hash', hash('sha256', $token))->first();
        $timestamp = (string) $request->header('X-Timestamp');
        $signature = (string) $request->header('X-Signature');
        $contentHash = (string) ($request->header('X-Content-Sha256') ?: hash('sha256', $request->getContent()));

        $fresh = ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= self::SKEW_SECONDS;
        $expected = $client === null ? '' : hash_hmac('sha256', implode('.', [$timestamp, strtoupper($request->getMethod()), '/'.$request->path(), $contentHash]), $client->secret);

        if ($client === null || ! $fresh || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Richiesta non autenticata.', 'codice' => 'firma_non_valida'], 401);
        }

        $client->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('agent_api_client', $client);

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AuthenticateApiToken — middleware for machine-to-machine BPM API calls.
 *
 * Expects:  Authorization: Bearer <plain-text-token>
 *
 * On success, injects the resolved ApiToken into the request as 'api_token',
 * and makes the owning Company available via 'api_company'.
 *
 * On failure, returns 401 JSON without leaking implementation details.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $plain = $request->bearerToken();

        if (empty($plain)) {
            return $this->unauthorized('Missing Bearer token.');
        }

        $apiToken = ApiToken::findByPlain($plain);

        if (! $apiToken) {
            return $this->unauthorized('Invalid or expired token.');
        }

        // Ability check — if the route requires specific scopes, validate them.
        foreach ($abilities as $ability) {
            if (! $apiToken->can($ability)) {
                return response()->json([
                    'error'   => 'Forbidden',
                    'message' => "This token does not have the '{$ability}' ability.",
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // Touch last_used_at without triggering mass-assignment protection
        $apiToken->timestamps = false;
        $apiToken->last_used_at = now();
        $apiToken->save();
        $apiToken->timestamps = true;

        // Inject into request for downstream use
        $request->attributes->set('api_token', $apiToken);
        $request->attributes->set('api_company', $apiToken->company);

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'error'   => 'Unauthorized',
            'message' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }
}

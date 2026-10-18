<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Accesso da unicobpm (prima su unicoloan): unicobpm apre /bpm-landing/{soggetto} con un token monouso, che si verifica con
 * `POST {BPM}/api/verify-token`. Se è valido e l'utente ha accesso al pannello dell'istruttoria, entra senza password.
 * Il token non finisce nei log; la verifica ha tempi massimi e richiede HTTPS; la sessione si rigenera dopo il login.
 */
class BpmBridgeController extends Controller
{
    public function handle(Request $request, string $subjectId): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:2048'],
            'user_email' => ['required', 'email', 'max:255'],
        ]);

        $bpmBaseUrl = rtrim((string) config('services.bpm.url'), '/');

        if (! str_starts_with($bpmBaseUrl, 'https://')) {
            Log::warning('BPM bridge: URL di verifica non HTTPS, richiesta rifiutata.');
            abort(403);
        }

        try {
            $response = Http::asJson()->timeout(8)->connectTimeout(4)
                ->post("{$bpmBaseUrl}/api/verify-token", ['token' => $validated['token'], 'email' => $validated['user_email']]);
        } catch (Throwable $e) {
            Log::warning('BPM bridge: verifica del token fallita per errore di rete.', ['error' => class_basename($e)]);
            abort(403);
        }

        if ($response->failed() || $response->json('valid') !== true) {
            $this->logAttempt($subjectId, success: false);
            abort(403, 'Token BPM non valido o scaduto.');
        }

        $user = User::query()->where('email', $validated['user_email'])->first();

        if (! $user || ! $user->canAccessPanel(filament()->getPanel('admin'))) {
            $this->logAttempt($subjectId, success: false);
            abort(403);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $this->logAttempt($subjectId, success: true, userId: $user->getKey());

        return redirect('/admin')->with('message', 'Accesso effettuato tramite BPM');
    }

    private function logAttempt(string $subjectId, bool $success, mixed $userId = null): void
    {
        Log::info($success ? 'BPM bridge: accesso riuscito.' : 'BPM bridge: accesso rifiutato.', array_filter([
            'subject_id' => $subjectId,
            'user_id' => $userId,
            'ip' => request()->ip(),
        ]));
    }
}

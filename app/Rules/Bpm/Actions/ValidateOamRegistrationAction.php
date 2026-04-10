<?php

declare(strict_types=1);

namespace App\Rules\Bpm\Actions;

use App\Contracts\BpmAction;
use App\Contracts\BpmActionInterface;
use App\Models\Agent;
use App\Models\TaskExecution;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ValidateOamRegistrationAction
 *
 * Validates that the Agent is properly registered in the OAM (Organismo
 * Agenti e Mediatori) registry before they can be activated.
 *
 * SIMULATION NOTE:
 * In production this would call the official OAM public API / web service
 * to verify that:
 *   1. The oam_number exists and is active
 *   2. The fiscal_code matches the registered entity
 *   3. The registration is not suspended or revoked
 *
 * For now we simulate the call with a configurable "success rate" via
 * the `oam_api.simulate_success` config flag (default: true in local/dev).
 * When the real OAM endpoint is available, replace the `simulateOamCheck()`
 * body with a real `Http::get(...)` call.
 *
 * If validation fails the action throws a RuntimeException — the BPM
 * observer catches it and prevents the checklist item from being checked.
 *
 * Registered in checklist_items.action_class as:
 *   App\Rules\Bpm\Actions\ValidateOamRegistrationAction
 */
final class ValidateOamRegistrationAction implements BpmAction, BpmActionInterface
{
    use ResolvesTargetAgent;

    /**
     * Base URL of the OAM public registry API.
     * Replace with the real endpoint when available.
     */
    private const OAM_API_BASE_URL = 'https://www.organismo-agenti.it/api/v1';

    public function execute(TaskExecution $execution, array $params = []): void
    {
        $agent = $this->resolveAgent($execution);

        if (blank($agent->oam_number)) {
            throw new \RuntimeException(
                'Agente non presente nel registro OAM o sospeso. Impossibile procedere.'
            );
        }

        $isValid = $this->verifyWithOamApi($agent);

        if (! $isValid) {
            throw new \RuntimeException(
                'Agente non presente nel registro OAM o sospeso. Impossibile procedere.'
            );
        }

        // Stamp the validation date on the agent record for audit purposes
        $agent->update(['oam_at' => now()]);

        Log::info('ValidateOamRegistrationAction: OAM validation passed', [
            'agent_id' => $agent->id,
            'oam_number' => $agent->oam_number,
        ]);
    }

    /**
     * Contact the OAM registry API to verify the agent's registration.
     *
     * In production, replace the simulation block with a real HTTP call:
     *
     *   $response = Http::baseUrl(self::OAM_API_BASE_URL)
     *       ->get('/verify', [
     *           'oam_number' => $agent->oam_number,
     *           'fiscal_code' => $agent->fiscal_code,
     *       ]);
     *
     *   return $response->json('valid') === true;
     */
    private function verifyWithOamApi(Agent $agent): bool
    {
        // ─────────────────────────────────────────────────────────
        //  SIMULATION BLOCK — replace when the real OAM API is ready
        // ─────────────────────────────────────────────────────────

        $simulateSuccess = (bool) config('oam_api.simulate_success', true);

        // Simulate network latency
        usleep(200_000); // 200ms

        // Log the simulated request for audit trail
        Log::debug('[SIMULATION] OAM API call', [
            'endpoint' => self::OAM_API_BASE_URL . '/verify',
            'params' => [
                'oam_number' => $agent->oam_number,
                'fiscal_code' => substr($agent->fiscal_code, 0, 4) . '****',
            ],
            'simulated_result' => $simulateSuccess ? 'VALID' : 'INVALID',
        ]);

        return $simulateSuccess;

        // ─────────────────────────────────────────────────────────
        //  PRODUCTION IMPLEMENTATION (uncomment when ready):
        //
        // try {
        //     $response = Http::baseUrl(self::OAM_API_BASE_URL)
        //         ->timeout(10)
        //         ->get('/verify', [
        //             'oam_number' => $agent->oam_number,
        //             'fiscal_code' => $agent->fiscal_code,
        //         ]);
        //
        //     if ($response->failed()) {
        //         Log::error('OAM API returned an error', [
        //             'status' => $response->status(),
        //             'body' => $response->body(),
        //         ]);
        //         return false;
        //     }
        //
        //     return $response->json('valid') === true
        //         && $response->json('status') === 'active';
        // } catch (\Throwable $e) {
        //     Log::error('OAM API unreachable', ['error' => $e->getMessage()]);
        //     return false;
        // }
        // ─────────────────────────────────────────────────────────
    }
}

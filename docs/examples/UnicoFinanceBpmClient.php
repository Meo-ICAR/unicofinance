<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * UnicoFinanceBpmClient
 *
 * HTTP client for triggering BPM process execution on UnicoFinance
 * from an external Laravel 13 application on a different database.
 *
 * ── Setup (in the calling app) ────────────────────────────────────────
 *
 * 1. Add to .env of the calling app:
 *      UNICOFINANCE_BPM_URL=https://unicofinance.example.com
 *      UNICOFINANCE_BPM_TOKEN=<plain-text-token-from-artisan-command>
 *
 * 2. Register in AppServiceProvider or a dedicated ServiceProvider:
 *      $this->app->singleton(UnicoFinanceBpmClient::class);
 *
 * 3. Inject via constructor or resolve from container:
 *      public function __construct(protected UnicoFinanceBpmClient $bpm) {}
 *
 * ── Usage ─────────────────────────────────────────────────────────────
 *
 *  // Create executions for all tasks of a process:
 *  $result = $this->bpm->createExecution(
 *      processId:      3,
 *      targetId:       $client->id,
 *      employeeId:     $employee->id,
 *      clientId:       $client->id,
 *      idempotencyKey: "onboarding-client-{$client->id}",
 *  );
 *
 *  // Poll later:
 *  $status = $this->bpm->getExecution($result['executions'][0]['id']);
 */
class UnicoFinanceBpmClient
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.unicofinance_bpm.url', env('UNICOFINANCE_BPM_URL', '')), '/');
        $this->token   = config('services.unicofinance_bpm.token', env('UNICOFINANCE_BPM_TOKEN', ''));

        if (empty($this->baseUrl) || empty($this->token)) {
            throw new RuntimeException(
                'UnicoFinanceBpmClient is not configured. '
                . 'Set UNICOFINANCE_BPM_URL and UNICOFINANCE_BPM_TOKEN in .env.'
            );
        }
    }

    /**
     * POST /api/bpm/executions
     *
     * Triggers creation of TaskExecution records for all ProcessTasks
     * belonging to the given Process on UnicoFinance.
     *
     * target_type is NOT required: it is derived from Process::target_model
     * on the UnicoFinance side, enforcing template integrity.
     *
     * @return array{
     *   success: bool,
     *   message: string,
     *   executions: array<array{
     *     id: int,
     *     process_task_id: int,
     *     target_type: string,
     *     target_id: int,
     *     employee_id: int|null,
     *     client_id: int|null,
     *     status: string,
     *     idempotency_key: string|null,
     *     checklist_items_count: int,
     *     created_at: string,
     *   }>
     * }
     *
     * @throws \RuntimeException  On HTTP error or non-201 response.
     */
    public function createExecution(
        int     $processId,
        int     $targetId,
        ?int    $employeeId     = null,
        ?int    $clientId       = null,
        ?string $idempotencyKey = null,
    ): array {
        $payload = array_filter([
            'process_id'      => $processId,
            'target_id'       => $targetId,
            'employee_id'     => $employeeId,
            'client_id'       => $clientId,
            'idempotency_key' => $idempotencyKey,
        ], fn ($v) => $v !== null);

        $response = $this->http()->post('/api/bpm/executions', $payload);

        $this->assertSuccess($response, 201, 'createExecution');

        return $response->json();
    }

    /**
     * GET /api/bpm/executions/{id}
     *
     * Polls the status of a TaskExecution and its checklist items.
     *
     * @return array{
     *   id: int,
     *   status: string,
     *   target_type: string,
     *   target_id: int,
     *   employee_id: int|null,
     *   client_id: int|null,
     *   started_at: string|null,
     *   completed_at: string|null,
     *   checklist_items: array,
     * }
     *
     * @throws \RuntimeException
     */
    public function getExecution(int $executionId): array
    {
        $response = $this->http()->get("/api/bpm/executions/{$executionId}");

        $this->assertSuccess($response, 200, 'getExecution');

        return $response->json();
    }

    /**
     * GET /api/bpm/executions/{id}/checklist
     *
     * Lists all runtime checklist items for an execution, including
     * the resolved evaluation of skip/require conditions.
     *
     * @return array{
     *   execution_id: int,
     *   status: string,
     *   items: array<array{
     *     id: int,
     *     checklist_item_id: int,
     *     instruction: string,
     *     action_class: string|null,
     *     has_action: bool,
     *     is_applicable: bool,
     *     is_mandatory: bool,
     *     require_overridden: bool,
     *     skip_reason: string|null,
     *     is_checked: bool,
     *     checked_at: string|null,
     *   }>
     * }
     *
     * @throws \RuntimeException
     */
    public function getChecklistItems(int $executionId): array
    {
        $response = $this->http()->get("/api/bpm/executions/{$executionId}/checklist");

        $this->assertSuccess($response, 200, 'getChecklistItems');

        return $response->json();
    }

    /**
     * GET /api/bpm/executions/{id}/checklist/{itemId}/evaluate
     *
     * Evaluate skip/require conditions for a specific runtime item
     * WITHOUT mutating anything. Use this for pre-flight checks.
     *
     * @return array{
     *   is_applicable: bool,
     *   is_mandatory: bool,
     *   skip_reason: string|null,
     *   require_overridden: bool,
     *   has_action: bool,
     *   action_class: string|null,
     *   runtime_item_id: int,
     *   is_checked: bool,
     *   checked_at: string|null,
     * }
     *
     * @throws \RuntimeException
     */
    public function evaluateChecklistItem(int $executionId, int $itemId): array
    {
        $response = $this->http()->get("/api/bpm/executions/{$executionId}/checklist/{$itemId}/evaluate");

        $this->assertSuccess($response, 200, 'evaluateChecklistItem');

        return $response->json();
    }

    /**
     * POST /api/bpm/executions/{id}/checklist/{itemId}/check
     *
     * Advances the checklist item:
     * 1. Evaluates skip_condition_class
     * 2. Evaluates require_condition_class
     * 3. Executes action_class (if any)
     * 4. Marks is_checked = true
     *
     * @param array<string,mixed> $params Optional parameters to pass to the action_class
     *
     * @return array{
     *   success: bool,
     *   skipped: bool,
     *   is_mandatory: bool,
     *   message: string,
     *   action_class: string|null,
     *   action_executed: bool,
     *   runtime_item_id: int,
     *   is_checked: bool,
     *   checked_at: string|null,
     * }
     *
     * @throws \RuntimeException
     */
    public function checkChecklistItem(int $executionId, int $itemId, array $params = []): array
    {
        $payload = empty($params) ? [] : ['params' => $params];

        $response = $this->http()->post("/api/bpm/executions/{$executionId}/checklist/{$itemId}/check", $payload);

        $this->assertSuccess($response, 200, 'checkChecklistItem');

        return $response->json();
    }

    /**
     * POST /api/bpm/executions/{id}/checklist/{itemId}/uncheck
     *
     * Reverts is_checked to false.
     * Does NOT re-run the action_class.
     *
     * @return array{
     *   success: bool,
     *   message: string,
     *   is_checked: bool,
     *   checked_at: string|null,
     * }
     *
     * @throws \RuntimeException
     */
    public function uncheckChecklistItem(int $executionId, int $itemId): array
    {
        $response = $this->http()->post("/api/bpm/executions/{$executionId}/checklist/{$itemId}/uncheck");

        $this->assertSuccess($response, 200, 'uncheckChecklistItem');

        return $response->json();
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->token)
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 500);
    }

    private function assertSuccess(Response $response, int $expectedStatus, string $method): void
    {
        if ($response->status() === 409) {
            throw new RuntimeException(
                "[UnicoFinanceBpmClient::{$method}] Idempotency conflict: "
                . ($response->json('message') ?? 'duplicate request')
            );
        }

        if ($response->status() !== $expectedStatus) {
            throw new RuntimeException(
                "[UnicoFinanceBpmClient::{$method}] Unexpected HTTP {$response->status()}: "
                . ($response->json('message') ?? $response->body())
            );
        }
    }
}

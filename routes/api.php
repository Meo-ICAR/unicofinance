<?php

use App\Http\Controllers\Api\BpmChecklistItemController;
use App\Http\Controllers\Api\BpmProcessExecutionController;
use App\Http\Controllers\Api\BpmTaskRunnerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. All paths are prefixed with /api.
|
*/

// ── Internal Task Runner UI (session-authenticated) ──────────────────────────
Route::middleware(['web', 'auth'])
    ->prefix('bpm/task-runner')
    ->name('api.bpm.task-runner.')
    ->group(function () {
        Route::get('/{taskExecution}', [BpmTaskRunnerController::class, 'show'])
            ->name('show');

        Route::post('/{taskExecution}/checklist/{checklistItem}/toggle', [BpmTaskRunnerController::class, 'toggleChecklistItem'])
            ->name('checklist.toggle');

        Route::post('/{taskExecution}/start', [BpmTaskRunnerController::class, 'start'])
            ->name('start');

        Route::post('/{taskExecution}/complete', [BpmTaskRunnerController::class, 'complete'])
            ->name('complete');
    });

// ─── Cross-App BPM API ───────────────────────────────────────────────────────
//
// Used by external Laravel 13 applications (different DB, same server).
// Authentication: Authorization: Bearer <plain-token>
// Tenant scope:   derived from token's company_id.
// Ability scope:  'bpm:create_execution'
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware(['api', 'auth.api_token:bpm:create_execution'])
    ->prefix('bpm/executions')
    ->name('api.bpm.executions.')
    ->group(function () {

        // POST   /api/bpm/executions
        //   → Create TaskExecution(s) for all ProcessTasks of a Process
        Route::post('/', [BpmProcessExecutionController::class, 'store'])
            ->name('store');

        // GET    /api/bpm/executions/{id}
        //   → Poll status + checklist summary of an execution
        Route::get('/{executionId}', [BpmProcessExecutionController::class, 'show'])
            ->name('show');

        // ── Checklist item sub-routes ─────────────────────────────────────────

        // GET    /api/bpm/executions/{id}/checklist
        //   → List all runtime checklist items with evaluated conditions
        Route::get('/{executionId}/checklist', [BpmChecklistItemController::class, 'index'])
            ->name('checklist.index');

        // GET    /api/bpm/executions/{id}/checklist/{itemId}/evaluate
        //   → Dry-run: evaluate skip/require conditions — no state mutation
        Route::get('/{executionId}/checklist/{itemId}/evaluate', [BpmChecklistItemController::class, 'evaluate'])
            ->name('checklist.evaluate');

        // POST   /api/bpm/executions/{id}/checklist/{itemId}/check
        //   → Advance item: skip → require → action_class → mark checked
        Route::post('/{executionId}/checklist/{itemId}/check', [BpmChecklistItemController::class, 'check'])
            ->name('checklist.check');

        // POST   /api/bpm/executions/{id}/checklist/{itemId}/uncheck
        //   → Revert is_checked (no action re-run)
        Route::post('/{executionId}/checklist/{itemId}/uncheck', [BpmChecklistItemController::class, 'uncheck'])
            ->name('checklist.uncheck');
    });


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

// BPM Task Runner — async checklist interaction (session-authenticated UI)
Route::middleware(['web', 'auth'])
    ->prefix('bpm/task-runner')
    ->name('api.bpm.task-runner.')
    ->group(function () {
        Route::get('/{taskExecution}', [BpmTaskRunnerController::class, 'show'])
            ->name('show');

        Route::post('/{taskExecution}/checklist/{checklistItem}/toggle', [BpmTaskRunnerController::class, 'toggleChecklistItem'])
            ->name('checklist.toggle');

        Route::post('/{taskExecution}/start', [BpmTaskRunnerController::class, 'start'])
            ->name('start');

        Route::post('/{taskExecution}/complete', [BpmTaskRunnerController::class, 'complete'])
            ->name('complete');
    });

// ─── Cross-App BPM Execution API ─────────────────────────────────────────────
//
// Used by external Laravel 13 applications (different DB, same server) to
// trigger TaskExecution creation on behalf of a tenant.
//
// Authentication: Bearer token via ApiToken model (SHA-256 hashed).
// Tenant scope:   derived from the token's company_id.
// Ability scope:  'bpm:create_execution'
//
// Example (from calling app):
//   POST https://unicofinance.example/api/bpm/executions
//   Authorization: Bearer <plain-token>
//   Content-Type:  application/json
//   { "process_id": 1, "target_id": 42, "employee_id": 7, "client_id": 3 }
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware(['api', 'auth.api_token:bpm:create_execution'])
    ->prefix('bpm/executions')
    ->name('api.bpm.executions.')
    ->group(function () {
        // Create new TaskExecution(s) for all tasks in the given Process
        Route::post('/', [BpmProcessExecutionController::class, 'store'])
            ->name('store');

        // Poll status of a specific execution
        Route::get('/{executionId}', [BpmProcessExecutionController::class, 'show'])
            ->name('show');
    });


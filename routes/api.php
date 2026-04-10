<?php

use App\Http\Controllers\Api\BpmTaskRunnerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

// BPM Task Runner — async checklist interaction
Route::middleware(['web', 'auth'])
    ->prefix('api/bpm/task-runner')
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

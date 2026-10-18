<?php

use App\Http\Controllers\Api\AgentRequestController;
use App\Http\Middleware\AuthenticateAgentApi;
use Illuminate\Support\Facades\Route;

Route::prefix('agente/v1')->middleware(AuthenticateAgentApi::class)->group(function () {
    Route::post('richieste', [AgentRequestController::class, 'store']);
    Route::post('richieste/{riferimento}/documenti', [AgentRequestController::class, 'storeDocument']);
    Route::get('richieste/{riferimento}/moduli', [AgentRequestController::class, 'modules']);
    Route::get('richieste/{riferimento}/moduli/{modulo}/template', [AgentRequestController::class, 'template']);
    Route::post('richieste/{riferimento}/moduli/{modulo}/compilato', [AgentRequestController::class, 'compile']);
    Route::post('richieste/{riferimento}/documenti/{documento}/firma', [AgentRequestController::class, 'sign']);
    Route::get('richieste/{riferimento}/documenti/{documento}/firma', [AgentRequestController::class, 'signature']);
    Route::get('richieste/{riferimento}/documenti/{documento}/file', [AgentRequestController::class, 'file']);
});

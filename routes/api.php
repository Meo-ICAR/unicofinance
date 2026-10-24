<?php

use App\Http\Controllers\Api\AgentDocumentController;
use App\Http\Controllers\Api\AgentRequestController;
use App\Http\Controllers\Api\ModelFieldsApiController;
use App\Http\Controllers\Api\ModelFieldValueApiController;
use App\Http\Controllers\Api\PraticaApiController;
use App\Http\Controllers\Api\PraticheInCorsoApiController;
use App\Http\Controllers\Api\UserLookupApiController;
use App\Http\Controllers\Api\WidgetTabellaApiController;
use Illuminate\Support\Facades\Route;
use Unico\Core\Bridge\Http\Middleware\CorrelationId;
use Unico\Core\Bridge\Http\Middleware\VerifyBridgeKey;

// Consumate da unicobpm (prima le chiamava su unicoloan): stessa chiave X-Api-Key del bridge di unico-core, correlazione nei log.
Route::middleware([CorrelationId::class, VerifyBridgeKey::class, 'throttle:300,1'])->group(function () {
    // Dati di una pratica per decidere se farla avanzare, con la blacklist dell'agente per la banca (azione blacklist_check).
    Route::get('/pratiche/{id}', [PraticaApiController::class, 'show'])->whereNumber('id')->name('api.pratiche.show');

    // Tabella delle pratiche in corso, globale o (con employee_id) di un solo istruttore: unicobpm decide a chi e quando inviarla.
    Route::get('/pratiche/in-corso', PraticheInCorsoApiController::class)->name('api.pratiche.in-corso');

    // Le tabelle dei widget della dashboard (istruttori e agenti), globali o per istruttore/agente: unicobpm decide a chi e quando inviarle.
    Route::get('/widgets', [WidgetTabellaApiController::class, 'index'])->name('api.widgets.index');
    Route::get('/widgets/{widget}', [WidgetTabellaApiController::class, 'show'])->name('api.widgets.show');

    // Campi di un modello (select dei processi) e scrittura di un campo a fine processo (completion_write_field).
    Route::get('/models/{model}/fields', [ModelFieldsApiController::class, 'show'])->name('api.models.fields');
    Route::patch('/models/{model}/{id}', [ModelFieldValueApiController::class, 'update'])->name('api.models.update-field');

    // Se l'utente di unicobpm ha un account anche qui (passaggio da un'app all'altra).
    Route::get('/users/lookup', [UserLookupApiController::class, 'show'])->name('api.users.lookup');
});

// Richieste di finanziamento consegnate da app esterne (unicoagent): cliente, pratica e documenti. Token Bearer e firma
// della richiesta (vedi config/agent_api.php e php artisan agent-api:client). Gli invii sono idempotenti per «riferimento».
Route::prefix('agente/v1')->middleware(['api.client', 'throttle:120,1'])->group(function () {
    Route::post('/richieste', [AgentRequestController::class, 'store'])->name('api.agente.richieste.store');
    Route::get('/richieste/{riferimento}', [AgentRequestController::class, 'show'])->name('api.agente.richieste.show');
    Route::patch('/richieste/{riferimento}', [AgentRequestController::class, 'update'])->name('api.agente.richieste.update');
    Route::post('/richieste/{riferimento}/note', [AgentRequestController::class, 'note'])->name('api.agente.richieste.note');
    Route::post('/richieste/{riferimento}/documenti', [AgentRequestController::class, 'upload'])->name('api.agente.richieste.documenti');
    Route::get('/tipi-documento', [AgentRequestController::class, 'documentTypes'])->name('api.agente.tipi-documento');

    // Funzioni documentali: moduli, template, modulo compilato, file e firma OTP.
    Route::get('/richieste/{riferimento}/moduli', [AgentDocumentController::class, 'modules'])->name('api.agente.moduli');
    Route::get('/richieste/{riferimento}/moduli/{modulo}/template', [AgentDocumentController::class, 'template'])->whereNumber('modulo')->name('api.agente.moduli.template');
    Route::post('/richieste/{riferimento}/moduli/{modulo}/compilato', [AgentDocumentController::class, 'fill'])->whereNumber('modulo')->name('api.agente.moduli.compila');
    Route::get('/richieste/{riferimento}/documenti/{documento}/file', [AgentDocumentController::class, 'file'])->name('api.agente.documenti.file');
    Route::post('/richieste/{riferimento}/documenti/{documento}/firma', [AgentDocumentController::class, 'requestSignature'])->name('api.agente.documenti.firma');
    Route::get('/richieste/{riferimento}/documenti/{documento}/firma', [AgentDocumentController::class, 'signature'])->name('api.agente.documenti.firma.stato');
});

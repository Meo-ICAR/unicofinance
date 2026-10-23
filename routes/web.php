<?php

use App\Http\Controllers\AgentiImpersonationController;
use App\Http\Controllers\BpmBridgeController;
use App\Http\Controllers\IstruttoriImpersonationController;
use App\Http\Controllers\ManualeController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::post('/agenti/impersonation/leave', [AgentiImpersonationController::class, 'leave'])
    ->name('agenti.impersonation.leave');

Route::post('/admin/istruttori/impersonation/leave', [IstruttoriImpersonationController::class, 'leave'])
    ->name('istruttori.impersonation.leave');

Route::get('/manuali/utente', [ManualeController::class, 'utente'])->name('manuali.utente');
Route::get('/manuali/tecnico', [ManualeController::class, 'tecnico'])->name('manuali.tecnico');

// Accesso da unicobpm con token monouso (prima su unicoloan). Il POST (che unicobpm usa da unicobpm#2) evita che il token
// resti in cronologia e nei log; il GET resta finché ci sono versioni di unicobpm che non mandano il POST. Throttling sui tentativi.
Route::match(['get', 'post'], '/bpm-landing/{subject_id}', [BpmBridgeController::class, 'handle'])
    ->middleware('throttle:10,1')
    ->name('bpm.landing');

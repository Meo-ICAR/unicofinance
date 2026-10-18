<?php

use App\Http\Controllers\AgentiImpersonationController;
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

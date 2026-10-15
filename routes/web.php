<?php

use App\Http\Controllers\AgentiImpersonationController;
use App\Http\Controllers\IstruttoriImpersonationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::post('/agenti/impersonation/leave', [AgentiImpersonationController::class, 'leave'])
    ->name('agenti.impersonation.leave');

Route::post('/admin/istruttori/impersonation/leave', [IstruttoriImpersonationController::class, 'leave'])
    ->name('istruttori.impersonation.leave');

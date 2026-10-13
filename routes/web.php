<?php

use App\Http\Controllers\AgentiImpersonationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/agenti/impersonation/leave', [AgentiImpersonationController::class, 'leave'])
    ->name('agenti.impersonation.leave');

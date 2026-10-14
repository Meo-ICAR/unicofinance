<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Risposte dei clienti con i documenti: si leggono le caselle di tipo «sollecito» e gli allegati si acquisiscono nella pratica.
Schedule::command('unico-core:mail-fetch --type=sollecito --sync')->everyFiveMinutes()->withoutOverlapping();

// Firme OTP della modulistica: lo stato delle richieste aperte si riallinea dal provider (anche senza webhook) e quelle scadute si chiudono.
Schedule::command('signature:sync')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('signature:check')->daily();

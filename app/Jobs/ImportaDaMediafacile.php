<?php

namespace App\Jobs;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

/**
 * Importa da Mediafacile pratiche e compensi degli ultimi 60 giorni (prima le pratiche, a cui si agganciano i compensi) e avvisa
 * l'utente che l'ha richiesto, con quante righe sono nuove o modificate. I numeri sono quelli del riepilogo che i comandi stampano.
 */
class ImportaDaMediafacile implements ShouldQueue
{
    use Dispatchable, Queueable;

    public const GIORNI = 60;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $userId) {}

    public function handle(): void
    {
        $opzioni = [
            '--start-date' => now()->subDays(self::GIORNI)->toDateString(),
            '--end-date' => now()->toDateString(),
        ];
        $righe = [];
        $fallito = false;

        foreach (['pratiche:import-api' => 'Pratiche', 'provvigioni:import-api' => 'Provvigioni'] as $comando => $etichetta) {
            try {
                $esito = Artisan::call($comando, $opzioni);
            } catch (\Throwable $e) {
                $esito = 1;
                report($e);
            }

            $output = Artisan::output();
            $ultima = trim((string) collect(explode("\n", trim($output)))->last());

            if ($esito !== 0) {
                $fallito = true;
                $righe[] = "{$etichetta}: importazione non riuscita. {$ultima}";

                continue;
            }

            if (preg_match('/Nuove: (\d+), aggiornate: (\d+)(?:, già lavorate: (\d+))?, errori: (\d+)/u', $output, $trovate) === 1) {
                $riga = "{$etichetta}: {$trovate[1]} nuove, {$trovate[2]} modificate";
                $riga .= $trovate[3] !== '' ? ", {$trovate[3]} già lavorate (non toccate)" : '';
                $righe[] = $riga.", {$trovate[4]} errori.";
            } else {
                $righe[] = "{$etichetta}: {$ultima}";
            }
        }

        $utente = User::query()->find($this->userId);

        if ($utente === null) {
            return;
        }

        $notifica = Notification::make()
            ->title($fallito ? 'Importazione da Mediafacile completata con errori' : 'Importazione da Mediafacile completata')
            ->body(implode("\n", $righe))
            ->color($fallito ? 'danger' : 'success')
            ->icon($fallito ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
            ->toDatabase();

        $utente->notifyNow($notifica);
    }
}

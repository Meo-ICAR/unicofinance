<?php

namespace App\Services\Importazione;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Lettura delle tabelle esportate da Mediafacile (pratiche e compensi): una richiesta per intervallo di date, risposta a testo con
 * una riga di intestazioni e i valori separati da tabulazioni.
 */
class MediafacileApi
{
    /**
     * @return array{intestazioni: list<string>, righe: list<list<string>>}
     */
    public function scarica(string $tabella, CarbonInterface $dal, CarbonInterface $al): array
    {
        $risposta = Http::withHeaders([
            'Accept' => 'application/json, */*',
            'User-Agent' => 'UnicoFinance Import/1.0',
            'X-Api-Key' => (string) config('services.mediafacile.header_key'),
        ])
            ->timeout(60)
            ->connectTimeout(10)
            ->withOptions(['http_errors' => false, 'verify' => false])
            ->retry(3, 1000, fn ($eccezione): bool => $eccezione instanceof ConnectionException || $eccezione->getCode() >= 500, throw: false)
            ->get((string) config('services.mediafacile.base_url'), [
                'table' => $tabella,
                'data_inizio' => $dal->format('Y-m-d'),
                'data_fine' => $al->format('Y-m-d'),
            ]);

        if (! $risposta->successful()) {
            Log::error("Mediafacile {$tabella}: errore API", ['status' => $risposta->status(), 'risposta' => substr($risposta->body(), 0, 1000)]);

            throw new RuntimeException('La richiesta all\'API è fallita con stato '.$risposta->status().'.');
        }

        $righe = array_values(array_filter(explode("\n", trim($risposta->body())), fn (string $riga): bool => trim($riga) !== ''));

        if ($righe === []) {
            throw new RuntimeException('L\'API non ha restituito dati.');
        }

        return [
            'intestazioni' => $this->valori($righe[0]),
            'righe' => array_map(fn (string $riga): array => $this->valori($riga), array_slice($righe, 1)),
        ];
    }

    /** @return list<string> */
    public function valori(string $riga): array
    {
        $parti = explode("\t", trim($riga));

        // Alcune esportazioni separano le colonne con più spazi invece che con tabulazioni.
        if (count($parti) <= 1) {
            $parti = preg_split('/\s{2,}/', trim($riga)) ?: [];
        }

        return array_map(fn (string $parte): string => trim($parte, " \t\n\r\0\v\"'`"), $parti);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Pratica;
use App\Support\NominativoDalCodiceFiscale;
use Illuminate\Console\Command;
use Unico\Core\Models\PraticaClient;

class CorreggiNominativi extends Command
{
    protected $signature = 'nominativi:correggi {--dry-run : Mostra quante schede cambierebbero senza modificare nulla}';

    protected $description = 'Rimette in ordine cognome e nome scambiati (riconosciuti dal codice fiscale) di pratiche, clienti e richiedenti già importati';

    public function handle(): int
    {
        $simulazione = (bool) $this->option('dry-run');
        $pratiche = $clienti = $richiedenti = 0;

        Pratica::query()->whereRaw('CHAR_LENGTH(codice_fiscale) = 16')->whereNotNull('nome_cliente')->whereNotNull('cognome_cliente')
            ->chunkById(500, function ($gruppo) use ($simulazione, &$pratiche): void {
                foreach ($gruppo as $pratica) {
                    [$cognome, $nome] = NominativoDalCodiceFiscale::ordina($pratica->cognome_cliente, $pratica->nome_cliente, $pratica->codice_fiscale);

                    if ($cognome === $pratica->cognome_cliente) {
                        continue;
                    }

                    $pratiche++;

                    if (! $simulazione) {
                        Pratica::query()->whereKey($pratica->getKey())->toBase()->update(['cognome_cliente' => $cognome, 'nome_cliente' => $nome]);
                    }
                }
            });

        // Il cliente tiene il cognome in `name` e il nome in `first_name`; la riga del richiedente sulla pratica li ha come «nome cognome».
        Client::query()->where('is_person', true)->whereRaw('CHAR_LENGTH(tax_code) = 16')->whereNotNull('first_name')->whereNotNull('name')
            ->chunkById(500, function ($gruppo) use ($simulazione, &$clienti, &$richiedenti): void {
                foreach ($gruppo as $cliente) {
                    [$cognome, $nome] = NominativoDalCodiceFiscale::ordina($cliente->name, $cliente->first_name, $cliente->tax_code);

                    if ($cognome === $cliente->name) {
                        continue;
                    }

                    $clienti++;
                    $righe = PraticaClient::query()->where('client_id', $cliente->getKey())->where('name', trim($cliente->first_name.' '.$cliente->name));
                    $richiedenti += (clone $righe)->count();

                    if (! $simulazione) {
                        $righe->update(['name' => trim($nome.' '.$cognome)]);
                        Client::query()->whereKey($cliente->getKey())->toBase()->update(['name' => $cognome, 'first_name' => $nome]);
                    }
                }
            });

        $this->info(($simulazione ? 'Da correggere' : 'Corrette').": pratiche {$pratiche}, clienti {$clienti}, richiedenti sulle pratiche {$richiedenti}.");

        return self::SUCCESS;
    }
}

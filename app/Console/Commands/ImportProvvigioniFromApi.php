<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Pratica;
use App\Models\Provvigione;
use App\Services\Importazione\MediafacileApi;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Unico\Core\Models\Company;
use Unico\Core\Models\Fornitore;

/**
 * Importa i compensi da Mediafacile nelle provvigioni di Proforma (database `proforma`, tabella `provvigioni`). Provvigioni e pratiche
 * stanno in database diversi: il legame comune è il codice della pratica (`id_pratica` = `codice_pratica`); `pratica_id` è la chiave esterna
 * verso le pratiche del database di Proforma e si imposta solo se la pratica c'è anche lì. Pratiche e produttori si leggono dal database
 * condiviso, per questo le query di aggiornamento ne qualificano le tabelle.
 */
class ImportProvvigioniFromApi extends Command
{
    protected $signature = 'provvigioni:import-api
                            {--start-date= : Data iniziale (AAAA-MM-GG)}
                            {--end-date= : Data finale (AAAA-MM-GG)}';

    protected $description = 'Importa i compensi (provvigioni) da Mediafacile';

    /** Intestazioni dell'esportazione dei compensi. */
    private const INTESTAZIONI = [
        'ID Compenso', 'Data Inserimento', 'Descrizione', 'Tipo', 'Importo', 'Importo Effettivo', 'Stato', 'Data Pagamento', 'N. Fattura',
        'Data Fattura', 'Data Status', 'Status Compenso', 'Denominazione Riferimento', 'Entrata Uscita', 'ID Pratica', 'Agente',
        'Istituto finanziario', 'Partita IVA Agente', 'Codice Fiscale Agente', 'ANNULLATA',
    ];

    public function handle(MediafacileApi $api): int
    {
        $al = $this->option('end-date') ? Carbon::parse($this->option('end-date')) : now();
        $dal = $this->option('start-date') ? Carbon::parse($this->option('start-date')) : $al->copy()->subDays(7);

        $this->info("Importazione provvigioni dal {$dal->format('Y-m-d')} al {$al->format('Y-m-d')}");

        try {
            $esportazione = $api->scarica('compensi', $dal, $al);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $mancanti = array_diff(self::INTESTAZIONI, $esportazione['intestazioni']);

        if ($mancanti !== []) {
            $this->error('Intestazioni mancanti nella risposta: '.implode(', ', $mancanti));
            $this->error('Intestazioni ricevute: '.implode(', ', $esportazione['intestazioni']));

            return self::FAILURE;
        }

        $intestazioni = $esportazione['intestazioni'];
        $conteggio = count($intestazioni);
        $righe = collect($esportazione['righe'])
            ->filter(fn (array $valori): bool => $valori !== [])
            ->map(fn (array $valori): array => array_combine($intestazioni, array_pad(array_slice($valori, 0, $conteggio), $conteggio, null)));

        if ($righe->isEmpty()) {
            $this->info('Nessun compenso nell\'intervallo indicato.');

            return self::SUCCESS;
        }

        $companyId = Company::query()->where('name', 'like', 'Races Finance%')->value('id') ?? Company::query()->value('id');
        $this->creaStatiMancanti($righe);
        $importate = $aggiornate = $saltate = $errori = 0;
        $adesso = now();

        foreach ($righe as $riga) {
            try {
                if (blank($riga['ID Compenso'])) {
                    $this->warn('Riga senza ID compenso: '.json_encode($riga));
                    $errori++;

                    continue;
                }

                $dati = $this->mappa($riga) + ['upload_at' => $adesso];
                $pratica = Pratica::query()->where('codice_pratica', $dati['id_pratica'])->first();

                // Un compenso senza la pratica nel database non si importa: arriverà con la pratica.
                if ($pratica === null) {
                    continue;
                }

                // `pratica_id` è il riferimento alle pratiche del database di Proforma (se ha la stessa pratica); il legame comune è il codice.
                $dati['pratica_id'] = DB::connection('proforma')->table('pratiches')->where('codice_pratica', $dati['id_pratica'])->value('id');

                if ($dati['tipo'] === 'Cliente') {
                    Client::query()->firstOrCreate(
                        ['name' => $dati['denominazione_riferimento']],
                        ['company_id' => $companyId, 'is_company' => 0, 'is_lead' => 0, 'is_client' => 1],
                    );
                }

                $dati['piva'] = $this->partitaIva($dati);
                $esistente = Provvigione::query()->find($dati['id']);

                if ($esistente === null) {
                    if ($dati['entrata_uscita'] === 'Uscita' && filled($dati['denominazione_riferimento'])) {
                        Fornitore::query()->firstOrCreate(['name' => $dati['denominazione_riferimento']], ['piva' => $dati['piva'], 'is_active' => true]);
                    }

                    (new Provvigione)->forceFill($dati)->save();
                    $importate++;

                    continue;
                }

                $esistente->update(['upload_at' => $adesso, 'piva' => $dati['piva']]);

                // Una provvigione già lavorata (con uno stato) non si riscrive: si registra solo la fattura arrivata dopo.
                if (blank($esistente->stato)) {
                    if (filled($pratica->erogated_at) && blank($esistente->erogated_at)) {
                        $dati['erogated_at'] = $pratica->erogated_at;
                        $dati['importo_erogato'] = $esistente->importo;
                    }

                    $esistente->update($dati);
                    $aggiornate++;

                    continue;
                }

                if (blank($esistente->n_fattura) && filled($dati['n_fattura'])) {
                    $esistente->update(['stato' => 'Pagato', 'status_pagamento' => 'Fatturato', 'n_fattura' => $dati['n_fattura'], 'data_fattura' => $dati['data_fattura']]);
                }

                $saltate++;
            } catch (\Throwable $e) {
                $this->error('Errore su un compenso: '.$e->getMessage());
                $errori++;
            }
        }

        $this->riallinea();
        $this->info("Importazione completata. Nuove: {$importate}, aggiornate: {$aggiornate}, già lavorate: {$saltate}, errori: {$errori}.");

        return self::SUCCESS;
    }

    /**
     * Stati dei compensi che mancano nelle tabelle di Proforma: quelli della provvigione (`provvigioni_statos`) e i «Status Compenso»
     * che arrivano dall'esportazione (`compensos`, con l'indicazione se il compenso è maturato dalla pratica perfezionata).
     *
     * @param  Collection<int, array<string, ?string>>  $righe
     */
    private function creaStatiMancanti($righe): void
    {
        $proforma = DB::connection('proforma');
        $creati = 0;

        foreach (['Inserito', 'Pagato', 'Sospeso'] as $stato) {
            $creati += $proforma->table('provvigioni_statos')->insertOrIgnore(['stato' => $stato]);
        }

        foreach ($righe->pluck('Status Compenso')->filter()->unique() as $stato) {
            $creati += $proforma->table('compensos')->insertOrIgnore(['status_compenso' => $stato, 'isperfezionato' => $stato === 'Pratica perfezionata']);
        }

        if ($creati > 0) {
            $this->info("Stati dei compensi aggiunti: {$creati}.");
        }
    }

    /**
     * Campi della provvigione ricavati dalla riga dell'esportazione.
     *
     * @param  array<string, ?string>  $riga
     * @return array<string, mixed>
     */
    protected function mappa(array $riga): array
    {
        $partitaIva = filled($riga['Partita IVA Agente']) ? $riga['Partita IVA Agente'] : (filled($riga['Codice Fiscale Agente']) ? $riga['Codice Fiscale Agente'] : null);
        $stato = $riga['Status Compenso'] ?? null;

        return [
            'id' => $riga['ID Compenso'],
            'data_inserimento_compenso' => $this->data($riga['Data Inserimento']),
            'descrizione' => $riga['Descrizione'],
            'tipo' => $riga['Tipo'] ?: 'provvigione',
            'importo' => $this->importo($riga['Importo']) ?? 0,
            'importo_effettivo' => $this->importo($riga['Importo Effettivo']),
            'status_pagamento' => $riga['Stato'] ?? '',
            'data_pagamento' => $this->data($riga['Data Pagamento']),
            'n_fattura' => $riga['N. Fattura'],
            'data_fattura' => $this->data($riga['Data Fattura']),
            'data_status' => $this->data($riga['Data Status']),
            'status_compenso' => $stato,
            'denominazione_riferimento' => $riga['Denominazione Riferimento'],
            'entrata_uscita' => $riga['Entrata Uscita'],
            // Il codice della pratica; il legame vero è `pratica_id`, che si imposta dopo aver trovato la pratica.
            'id_pratica' => $riga['ID Pratica'],
            'segnalatore' => $riga['Agente'],
            'istituto_finanziario' => $riga['Istituto finanziario'],
            'piva' => $partitaIva,
            'cf' => $riga['Codice Fiscale Agente'],
            'annullato' => $stato === 'Stornata',
            'fonte' => 'mediafacile',
            'coordinamento' => $riga['Agente'] != $riga['Denominazione Riferimento'],
            'iscliente' => str_contains((string) $riga['Descrizione'], 'liente'),
        ];
    }

    /** Per un compenso in uscita senza partita IVA valida si prende quella del produttore, se la conosciamo. */
    private function partitaIva(array $dati): ?string
    {
        $piva = trim((string) $dati['piva']);

        if ($dati['entrata_uscita'] !== 'Uscita' || strlen($piva) === 11) {
            return $piva === '' ? null : $piva;
        }

        $produttore = Fornitore::query()->where('name', $dati['denominazione_riferimento'])->first();

        if ($produttore !== null && $produttore->piva !== $piva && strlen((string) $produttore->piva) === 11) {
            return $produttore->piva;
        }

        return $piva === '' ? null : $piva;
    }

    /** Allinea stati, erogazioni, partite IVA e fatture dei compensi (tabelle di Proforma; pratiche e produttori si leggono dal database condiviso). */
    private function riallinea(): void
    {
        $proforma = DB::connection('proforma');

        $this->info($this->eliminaDuplicati($proforma, true).' compensi duplicati senza fattura eliminati.');
        $this->info($this->eliminaDuplicati($proforma, false).' compensi duplicati eliminati.');

        $righe = $proforma->update("UPDATE provvigioni p SET p.stato = 'Inserito', p.deleted_at = NULL WHERE p.stato IS NULL AND p.status_compenso = 'Pratica perfezionata'");
        $this->info("{$righe} compensi portati in stato Inserito.");

        $righe = $proforma->update("UPDATE provvigioni p SET p.stato = 'Pagato', p.deleted_at = NULL, p.status_pagamento = 'Fatturato' WHERE p.data_fattura IS NOT NULL");
        $this->info("{$righe} compensi portati in stato Pagato.");

        $this->annullaSenzaPratica($proforma);
        $this->info($this->allineaErogazioni($proforma).' compensi allineati all\'erogazione della pratica.');
        $this->info($this->allineaPartiteIva($proforma).' partite IVA allineate ai produttori.');

        $righe = $proforma->update('UPDATE provvigioni f INNER JOIN proformas p ON p.id = f.proforma_id INNER JOIN sales_invoices s ON p.invoiceable_id = s.id AND p.invoiceable_type = ? SET f.data_pagamento = s.registration_date, f.n_fattura = s.number, f.data_fattura = s.registration_date, f.status_pagamento = ?', ['App\\Models\\SalesInvoice', 'Fatturato']);
        $this->info("{$righe} compensi fatturati da proforma (fatture attive).");

        $righe = $proforma->update('UPDATE provvigioni f INNER JOIN proformas p ON p.id = f.proforma_id INNER JOIN purchase_invoices s ON p.invoiceable_id = s.id AND p.invoiceable_type = ? SET f.data_pagamento = s.registration_date, f.n_fattura = COALESCE(s.supplier_invoice_number, s.number), f.data_fattura = s.registration_date, f.status_pagamento = ?', ['App\\Models\\PurchaseInvoice', 'Fatturato']);
        $this->info("{$righe} compensi fatturati da proforma (fatture passive).");
    }

    /**
     * Data di erogazione della pratica sui suoi compensi. Le pratiche sono in un altro database: si leggono e si raggruppano per data,
     * invece di fare una join tra database.
     */
    private function allineaErogazioni($connessione): int
    {
        $aggiornati = 0;

        Pratica::query()->whereNotNull('erogated_at')->get(['codice_pratica', 'erogated_at'])
            ->groupBy(fn (Pratica $pratica): string => $pratica->erogated_at->toDateString())
            ->each(function ($pratiche, string $data) use ($connessione, &$aggiornati): void {
                foreach ($pratiche->pluck('codice_pratica')->chunk(500) as $codici) {
                    $aggiornati += $connessione->table('provvigioni')
                        ->whereIn('id_pratica', $codici->all())
                        ->where(fn ($query) => $query->whereNull('erogated_at')->orWhere('erogated_at', '<>', $data))
                        ->update(['erogated_at' => $data, 'importo_erogato' => DB::raw('importo')]);
                }
            });

        return $aggiornati;
    }

    /**
     * Compensi di Mediafacile che puntano a una pratica che non esiste più nel database condiviso: si segnano come annullati (respinti).
     * Quelli già fatturati o pagati non si toccano e si segnalano.
     */
    private function annullaSenzaPratica($connessione): void
    {
        $orfani = collect();

        $connessione->table('provvigioni')->where('fonte', 'mediafacile')->where('annullato', false)->whereNull('deleted_at')->distinct()->pluck('id_pratica')
            ->chunk(1000)
            ->each(function ($codici) use (&$orfani): void {
                $esistenti = Pratica::query()->whereIn('codice_pratica', $codici->all())->pluck('codice_pratica');
                $orfani = $orfani->merge($codici->diff($esistenti));
            });

        if ($orfani->isEmpty()) {
            return;
        }

        $annullati = 0;
        $lavorati = 0;

        foreach ($orfani->chunk(1000) as $codici) {
            $base = $connessione->table('provvigioni')->where('fonte', 'mediafacile')->where('annullato', false)->whereIn('id_pratica', $codici->all());

            $lavorati += (clone $base)->where(fn ($query) => $query->whereNotNull('data_fattura')->orWhere('stato', 'Pagato'))->count();
            $annullati += $base->whereNull('data_fattura')->where(fn ($query) => $query->whereNull('stato')->orWhere('stato', '<>', 'Pagato'))->update(['annullato' => true]);
        }

        $this->warn("Compensi di pratiche inesistenti, segnati come annullati: {$annullati}.");

        if ($lavorati > 0) {
            $this->warn("Compensi di pratiche inesistenti già fatturati o pagati (non toccati): {$lavorati}.");
        }
    }

    /** Partita IVA del produttore (11 cifre) sui compensi in uscita a suo nome, se diversa. */
    private function allineaPartiteIva($connessione): int
    {
        $aggiornati = 0;

        Fornitore::query()->whereRaw('length(piva) = 11')->get(['name', 'piva'])->each(function (Fornitore $produttore) use ($connessione, &$aggiornati): void {
            $aggiornati += $connessione->table('provvigioni')
                ->where('denominazione_riferimento', $produttore->name)
                ->where('entrata_uscita', 'Uscita')
                ->where('piva', '<>', $produttore->piva)
                ->update(['piva' => $produttore->piva]);
        });

        return $aggiornati;
    }

    /**
     * Di più compensi con la stessa pratica, tipo, riferimento e descrizione, senza fattura né proforma e non stornati, resta il più recente
     * (o, con $vecchi = false, il più vecchio: il secondo passaggio ripulisce quelli rimasti).
     */
    private function eliminaDuplicati($connessione, bool $vecchi): int
    {
        $confronto = $vecchi ? 'p1.id < p2.id' : 'p1.id > p2.id';

        return $connessione->delete("DELETE p1 FROM provvigioni p1 INNER JOIN provvigioni p2 ON p1.id_pratica = p2.id_pratica AND p1.tipo = p2.tipo AND p1.denominazione_riferimento = p2.denominazione_riferimento AND p1.descrizione = p2.descrizione WHERE {$confronto} AND p1.data_fattura IS NULL AND p1.proforma_id IS NULL AND p1.status_compenso <> 'Stornata'");
    }

    private function data(?string $valore): ?string
    {
        if (blank($valore)) {
            return null;
        }

        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $valore)) {
                return Carbon::createFromFormat('d/m/Y', $valore)->toDateString();
            }

            return strtotime($valore) ? Carbon::parse($valore)->toDateString() : null;
        } catch (\Exception $e) {
            $this->warn("Data non riconosciuta «{$valore}»: ".$e->getMessage());

            return null;
        }
    }

    private function importo(?string $valore): ?float
    {
        if (blank($valore)) {
            return null;
        }

        return is_numeric($valore) ? (float) $valore : (float) str_replace(',', '.', $valore);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Pratica;
use App\Services\Importazione\MediafacileApi;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use RuntimeException;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Company;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\PraticaStato;
use Unico\Core\Models\TipoProdotto;

/**
 * Importa le pratiche da Mediafacile nel database condiviso (`pratiches`), comprese quelle di terzi (`is_notowned`), con società Races Finance. Il tipo prodotto, lo stato, il produttore e la banca
 * non sono più testo sulla pratica ma riferimenti (`tipoprodotto_id`, `pratica_stato_id`, `fornitori_id`, `clienti_id`): si cercano
 * nelle anagrafiche e, per produttori, banche e stati, si creano se mancano. Ogni cambio di stato entra nello storico.
 */
class ImportPraticheFromApi extends Command
{
    protected $signature = 'pratiche:import-api
                            {--start-date= : Data iniziale (AAAA-MM-GG)}
                            {--end-date= : Data finale (AAAA-MM-GG)}
                            {--no-close : Non chiudere le pratiche che non compaiono più nell\'esportazione}';

    protected $description = 'Importa le pratiche da Mediafacile';

    /** @var Collection<string, TipoProdotto|null> */
    private Collection $prodotti;

    /** @var list<string> */
    private array $prodottiSconosciuti = [];

    public function handle(MediafacileApi $api): int
    {
        $al = $this->option('end-date') ? Carbon::parse($this->option('end-date')) : now();
        $dal = $this->option('start-date') ? Carbon::parse($this->option('start-date')) : $al->copy()->subDays(60);
        $this->prodotti = collect();

        $this->info("Importazione pratiche dal {$dal->format('Y-m-d')} al {$al->format('Y-m-d')}");

        try {
            $esportazione = $api->scarica('pratiche', $dal, $al);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $righe = collect($esportazione['righe'])
            ->filter(fn (array $valori): bool => count($valori) === count($esportazione['intestazioni']))
            ->map(fn (array $valori): array => array_combine($esportazione['intestazioni'], $valori));

        if ($righe->isEmpty()) {
            $this->info('Nessuna pratica nell\'intervallo indicato.');

            return self::SUCCESS;
        }

        $companyId = $this->company();
        $this->creaStatiMancanti($righe);
        $importate = $aggiornate = $errori = 0;
        $adesso = now();

        foreach ($righe as $riga) {
            try {
                $dati = $this->mappa($riga);

                if (blank($dati['codice_pratica'])) {
                    $this->warn('Riga senza ID pratica: '.json_encode($riga));
                    $errori++;

                    continue;
                }

                $dati = $dati + $this->riferimenti($dati, $riga) + ['company_id' => $companyId, 'upload_at' => $adesso];
                $this->creaCliente($dati, $companyId);
                $pratica = Pratica::query()->where('codice_pratica', $dati['codice_pratica'])->first();

                if ($pratica === null) {
                    $pratica = Pratica::query()->create($dati);
                    $this->registraStato($pratica, null);
                    $importate++;

                    continue;
                }

                $statoPrecedente = $pratica->pratica_stato_id;
                $pratica->update($dati);
                $this->registraStato($pratica, $statoPrecedente);
                $aggiornate++;
            } catch (\Throwable $e) {
                $this->error('Errore su una pratica: '.$e->getMessage());
                $errori++;
            }
        }

        foreach ($this->prodottiSconosciuti as $prodotto) {
            $this->warn("Tipo prodotto non presente tra i prodotti: «{$prodotto}» (pratiche importate senza prodotto).");
        }

        if (! $this->option('no-close')) {
            $this->chiudiScomparse($righe->pluck('ID Pratica')->filter()->all(), $dal, $al, $adesso);
        }

        $this->info("Importazione completata. Nuove: {$importate}, aggiornate: {$aggiornate}, errori: {$errori}.");

        return self::SUCCESS;
    }

    /** Codici delle pratiche di Mediafacile (QT + numero); quelle create nell'app (SIM-…) non dipendono dall'esportazione. */
    private const CODICE_MEDIAFACILE = '^QT[0-9]+$';

    /**
     * Una pratica di Mediafacile ancora aperta, inserita nell'intervallo importato e assente dall'esportazione di oggi non esiste più
     * lì: si segna come respinta (`rejected_at` = oggi). Non si toccano le pratiche create nell'app, già erogate o già respinte.
     *
     * @param  list<string>  $codiciVisti
     */
    private function chiudiScomparse(array $codiciVisti, Carbon $dal, Carbon $al, Carbon $adesso): void
    {
        $scomparse = Pratica::query()
            ->whereRaw('codice_pratica REGEXP ?', [self::CODICE_MEDIAFACILE])
            ->whereBetween('data_inserimento_pratica', [$dal->copy()->startOfDay(), $al->copy()->endOfDay()])
            ->whereNull('rejected_at')
            ->whereNull('erogated_at')
            ->where('upload_at', '<', $adesso)
            ->whereNotIn('codice_pratica', $codiciVisti);

        $chiuse = $scomparse->update(['rejected_at' => today()]);

        if ($chiuse > 0) {
            $this->warn("Pratiche non più presenti in Mediafacile, segnate come respinte: {$chiuse}.");
        }
    }

    /** Races Finance è la società di tutte le pratiche importate. */
    private function company(): ?int
    {
        $id = Company::query()->where('name', 'like', 'Races Finance%')->value('id') ?? Company::query()->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Gli stati pratica che l'esportazione usa e che non esistono ancora (per prodotto) si aggiungono prima di importare.
     *
     * @param  Collection<int, array<string, string>>  $righe
     */
    private function creaStatiMancanti(Collection $righe): void
    {
        $creati = 0;

        $righe->map(fn (array $riga): array => [trim((string) ($riga['Stato Pratica'] ?? '')), $this->prodotto(trim((string) ($riga['Tipo Prodotto'] ?? '')))?->name ?? trim((string) ($riga['Tipo Prodotto'] ?? ''))])
            ->filter(fn (array $coppia): bool => $coppia[0] !== '')
            ->unique(fn (array $coppia): string => $coppia[1].'|'.$coppia[0])
            ->each(function (array $coppia) use (&$creati): void {
                if (! PraticaStato::query()->where('codice', $coppia[0])->where('tipo_finanziamento', $coppia[1])->exists()) {
                    $this->stato($coppia[0], $coppia[1]);
                    $creati++;
                }
            });

        if ($creati > 0) {
            $this->info("Stati pratica aggiunti: {$creati}.");
        }
    }

    /**
     * Il richiedente è un cliente del database condiviso, riconosciuto dal codice fiscale (o dalla partita IVA): se non c'è si crea;
     * se c'è non si modifica.
     *
     * @param  array<string, mixed>  $dati
     */
    private function creaCliente(array $dati, ?int $companyId): void
    {
        $codice = strtoupper(preg_replace('/\s+/', '', (string) $dati['codice_fiscale']));

        if (! in_array(strlen($codice), [11, 16], true)) {
            return;
        }

        Client::query()->firstOrCreate(['tax_code' => $codice], [
            'name' => $dati['cognome_cliente'] ?: ($dati['nome_cliente'] ?: $codice),
            'first_name' => strlen($codice) === 16 ? $dati['nome_cliente'] : null,
            'is_person' => strlen($codice) === 16,
            'company_id' => $companyId,
        ]);
    }

    /**
     * Campi della pratica ricavati dalla riga dell'esportazione (senza i riferimenti alle anagrafiche).
     *
     * @param  array<string, string>  $riga
     * @return array<string, mixed>
     */
    protected function mappa(array $riga): array
    {
        $partitaIva = $riga['Partita IVA Agente'] ?? null;

        return [
            // La pratica si identifica con il suo codice (pratiches.codice_pratica); l'id intero lo assegna il database.
            'codice_pratica' => $riga['ID Pratica'] ?? null,
            'nome_cliente' => $riga['Nome Cliente'] ?? null,
            'cognome_cliente' => $riga['Cognome Cliente'] ?? null,
            'codice_fiscale' => $riga['Codice Fiscale'] ?? null,
            'denominazione_agente' => $riga['Denominazione Agente'] ?? null,
            'partita_iva_agente' => (blank($partitaIva) || $partitaIva < '0') ? '---' : $partitaIva,
            'denominazione_banca' => $riga['Denominazione Banca'] ?? null,
            'denominazione_prodotto' => $riga['Descrizione Prodotto'] ?? null,
            'data_inserimento_pratica' => $this->data($riga['Data Inserimento Pratica'] ?? null) ?? now(),
            'sended_at' => $this->data($riga['Data_invio_istruttoria'] ?? null),
            'approved_at' => $this->data($riga['Data_delibera_banca'] ?? null),
            'erogated_at' => $this->data($riga['Data_erogazione'] ?? null),
            'rejected_at' => $this->data($riga['Data Annullamento'] ?? null),
            'amount' => $this->decimale($riga['Montante'] ?? null),
            'net' => $this->decimale($riga['Importo_Erogazione'] ?? null),
            'is_notowned' => ($riga['Pratica_terzi'] ?? '') === 'SI',
        ];
    }

    /**
     * Tipo prodotto, stato, produttore e banca come riferimenti.
     *
     * @param  array<string, mixed>  $dati
     * @param  array<string, string>  $riga
     * @return array<string, mixed>
     */
    private function riferimenti(array $dati, array $riga): array
    {
        $nomeProdotto = trim((string) ($riga['Tipo Prodotto'] ?? ''));
        $prodotto = $this->prodotto($nomeProdotto);
        $codiceStato = trim((string) ($riga['Stato Pratica'] ?? ''));

        return [
            'tipoprodotto_id' => $prodotto?->getKey(),
            'pratica_stato_id' => $codiceStato === '' ? null : $this->stato($codiceStato, $prodotto?->name ?? $nomeProdotto)->getKey(),
            'fornitori_id' => $this->produttore((string) $dati['denominazione_agente'], (string) $dati['partita_iva_agente'])?->getKey(),
            'clienti_id' => blank($dati['denominazione_banca']) ? null : Cliente::query()->firstOrCreate(['name' => $dati['denominazione_banca']])->getKey(),
        ];
    }

    private function prodotto(string $nome): ?TipoProdotto
    {
        if ($nome === '') {
            return null;
        }

        return $this->prodotti->get($nome, function () use ($nome): ?TipoProdotto {
            $prodotto = TipoProdotto::query()->whereRaw('lower(name) = ?', [mb_strtolower($nome)])->orWhereRaw('lower(tipo_prodotto) = ?', [mb_strtolower($nome)])->first();

            if ($prodotto === null) {
                $this->prodottiSconosciuti[] = $nome;
            }

            return tap($prodotto, fn (?TipoProdotto $trovato) => $this->prodotti->put($nome, $trovato));
        });
    }

    /** Gli stati dei finanziamenti sono per prodotto: se manca per questo prodotto si crea, come fa il perfezionamento. */
    private function stato(string $codice, string $prodotto): PraticaStato
    {
        return PraticaStato::query()->firstOrCreate(
            ['codice' => $codice, 'tipo_finanziamento' => $prodotto],
            ['name' => $codice, 'ordine' => 0, 'is_rejected' => false, 'is_working' => true, 'is_estingued' => false, 'colore' => 'gray'],
        );
    }

    /** Il produttore si riconosce dalla partita IVA, altrimenti dal nome; se non c'è si crea. */
    private function produttore(string $nome, string $partitaIva): ?Fornitore
    {
        if ($nome === '') {
            return null;
        }

        $valida = in_array(strlen($partitaIva), [11, 16], true);

        return ($valida ? Fornitore::query()->where('piva', $partitaIva)->first() : null)
            ?? Fornitore::query()->where('name', $nome)->first()
            ?? Fornitore::query()->create(['name' => $nome, 'piva' => $valida ? $partitaIva : null, 'is_active' => true]);
    }

    /** Storico degli stati: una riga alla creazione e a ogni cambio di stato. */
    private function registraStato(Pratica $pratica, int|string|null $precedente): void
    {
        if ((string) $precedente === (string) $pratica->pratica_stato_id || $pratica->pratica_stato_id === null) {
            return;
        }

        $pratica->praticaStatusHistory()->create([
            'status_from' => $precedente === null ? null : PraticaStato::query()->whereKey($precedente)->value('name'),
            'status_to' => (string) PraticaStato::query()->whereKey($pratica->pratica_stato_id)->value('name'),
            'changed_at' => now(),
            'source' => 'mediafacile',
            'notes' => 'Importazione da Mediafacile',
        ]);
    }

    private function data(?string $valore): ?Carbon
    {
        if (blank($valore)) {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'd/m/Y H:i:s', 'Y-m-d H:i:s'] as $formato) {
            try {
                return Carbon::createFromFormat($formato, trim($valore));
            } catch (\Exception) {
                // si prova il formato successivo
            }
        }

        try {
            return new Carbon($valore);
        } catch (\Exception) {
            $this->warn("Data non riconosciuta: {$valore}");

            return null;
        }
    }

    private function decimale(?string $valore): ?float
    {
        if (blank($valore)) {
            return null;
        }

        $pulito = str_replace(['.', ','], ['', '.'], trim($valore));

        return is_numeric($pulito) ? (float) $pulito : null;
    }
}

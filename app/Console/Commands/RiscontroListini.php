<?php

namespace App\Console\Commands;

use App\Services\Provvigioni\PercentualeListino;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\TipoProdotto;

class RiscontroListini extends Command
{
    protected $signature = 'listini:riscontro {--dal=2026-07-01 : Solo le pratiche inserite da questa data (AAAA-MM-GG)}';

    protected $description = 'Produttori attivi con pratiche recenti su un prodotto per cui non hanno un listino provvigionale assegnato';

    public function handle(PercentualeListino $percentuali): int
    {
        $senza = $percentuali->senzaRiscontro(Carbon::parse((string) $this->option('dal')));

        if ($senza->isEmpty()) {
            $this->info('Tutti i produttori hanno un listino per i prodotti che trattano.');

            return self::SUCCESS;
        }

        $fornitori = Fornitore::query()->whereIn('id', $senza->pluck('fornitore_id'))->pluck('name', 'id');
        $prodotti = TipoProdotto::query()->whereIn('id', $senza->pluck('tipoprodotto_id'))->pluck('name', 'id');

        $this->table(
            ['Produttore', 'Prodotto', 'Pratiche'],
            $senza->sortBy([['fornitore_id', 'asc'], ['pratiche', 'desc']])->map(fn (array $riga): array => [$fornitori[$riga['fornitore_id']] ?? $riga['fornitore_id'], $prodotti[$riga['tipoprodotto_id']] ?? $riga['tipoprodotto_id'], $riga['pratiche']])->all(),
        );
        $this->warn($senza->count().' combinazioni produttore-prodotto senza listino ('.$senza->sum('pratiche').' pratiche).');

        return self::FAILURE;
    }
}

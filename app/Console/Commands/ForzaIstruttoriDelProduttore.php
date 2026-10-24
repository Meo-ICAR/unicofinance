<?php

namespace App\Console\Commands;

use App\Services\Istruttoria\IstruttoreDelProduttore;
use Illuminate\Console\Command;

class ForzaIstruttoriDelProduttore extends Command
{
    protected $signature = 'pratiche:forza-istruttori-produttore';

    protected $description = 'Imposta come istruttore il produttore stesso nelle pratiche dei produttori indicati in config/istruttoria.php (FUMMO BRIGIDA, ZACCARIA LOREDANA)';

    public function handle(IstruttoreDelProduttore $istruttori): int
    {
        $this->info('Pratiche con l\'istruttore forzato: '.$istruttori->forzaTutte().'.');

        return self::SUCCESS;
    }
}

<?php

namespace Tests;

use App\Models\Provvigione;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // I test non devono mai toccare il database vero: con la cache di configurazione attiva si userebbe quello di sviluppo.
        foreach (array_unique([config('database.default'), config('unico-core.connection')]) as $connection) {
            $database = (string) config("database.connections.{$connection}.database");

            if ($database !== ':memory:' && ! str_ends_with($database, '_test')) {
                throw new \RuntimeException("Test interrotto: la connessione \"{$connection}\" punta a \"{$database}\", che non è un database di prova.");
            }
        }
    }

    /** Compenso in uscita del produttore, così la pratica resta nel suo elenco. */
    protected function collegaProvvigioneProduttore(Fornitore $fornitore, Pratica $pratica): void
    {
        (new Provvigione)->forceFill([
            'id' => (string) Str::uuid(),
            'id_pratica' => $pratica->codice_pratica,
            'importo' => 1,
            'entrata_uscita' => 'Uscita',
            'piva' => $fornitore->piva,
            'denominazione_riferimento' => $fornitore->name,
            'annullato' => false,
        ])->save();
    }
}

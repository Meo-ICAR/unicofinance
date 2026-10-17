<?php

namespace Tests;

use App\Models\Provvigione;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;

abstract class TestCase extends BaseTestCase
{
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

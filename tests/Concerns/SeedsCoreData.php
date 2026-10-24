<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Storage;
use Unico\Core\Seeders\CoreDataSeeder;

/**
 * Carica nel test alcune tabelle di base del core (prodotti, stati pratica, moduli PDF, tipi documento, tipi indirizzo...) dai JSON
 * di unico-core. Nessuna migration le inserisce: un database ricreato da zero è vuoto. Si caricano solo quelle che il test usa
 * (gli altri test creano le proprie righe e cozzerebbero con le chiavi univoche); il test le annulla a fine esecuzione.
 */
trait SeedsCoreData
{
    /**
     * @param  string  ...$tables  Nomi delle tabelle di CoreDataSeeder::TABLES.
     */
    protected function seedCoreData(string ...$tables): void
    {
        (new class extends CoreDataSeeder
        {
            /** @param  list<string>  $tables */
            public function only(array $tables): void
            {
                $this->load($tables);
            }
        })->only($tables);
    }

    /**
     * I modelli PDF dei moduli (QAV, fascicoli, conteggio estintivo...) sono file caricati nell'ambiente, non stanno nel repository:
     * i test che stampano un modulo vero si saltano se mancano.
     */
    protected function skipWithoutPdfTemplates(): void
    {
        if (! Storage::disk((string) config('unico-core.pdf.modules_disk', 'public'))->exists('module/QAV Persona fisica_compressed.pdf')) {
            $this->markTestSkipped('Mancano i modelli PDF dei moduli (storage/app/public/module): vanno copiati dall\'ambiente di produzione.');
        }
    }
}

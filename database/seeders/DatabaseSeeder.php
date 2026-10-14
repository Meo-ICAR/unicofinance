<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Unico\Core\Seeders\ChecklistPerfezionamentoSeeder;
use Unico\Core\Seeders\CoreDataSeeder;
use Unico\Core\Seeders\FascicoloCqMappingSeeder;
use Unico\Core\Seeders\PerfezionamentoDocumentiSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Ricostruisce i dati di base dopo un reset: prima il core condiviso (migrazioni del pacchetto:
     * `php artisan unico-core:migrate`), poi i dati propri dell'app. Pratiche e provvigioni non si ricaricano.
     */
    public function run(): void
    {
        $this->call([
            CoreDataSeeder::class,
            PerfezionamentoDocumentiSeeder::class,
            ChecklistPerfezionamentoSeeder::class,
            FascicoloCqMappingSeeder::class,
            ListiniProvvigionaliSeeder::class,
        ]);
    }
}

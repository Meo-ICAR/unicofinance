<?php

namespace App\Providers;

use App\Models\ListinoProvvigionale;
use App\Pdf\KycModuleDataProvider;
use Illuminate\Support\ServiceProvider;
use Unico\Core\Models\Fornitore;
use Unico\Core\UnicoCoreServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->tag(KycModuleDataProvider::class, 'unico-core.module-data-providers');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Il modello Fornitore è del pacchetto core: i listini si aggiungono come relazione dinamica.
        Fornitore::resolveRelationUsing('listiniProvvigionali', fn (Fornitore $fornitore) => $fornitore
            ->belongsToMany(ListinoProvvigionale::class, 'fornitori_listini_provvigionali', 'fornitori_id', 'listino_provvigionale_id')
            ->withPivot('coordinamento')
            ->withTimestamps());

        // Le tabelle condivise stanno su unico_core_test. Si caricano le migration del
        // pacchetto solo se UNICO_CORE_MIGRATE=true (database proprio o test).
        if (config('database.unico_core_migrate')) {
            $this->loadMigrationsFrom(UnicoCoreServiceProvider::migrationsPath());
        }
    }
}

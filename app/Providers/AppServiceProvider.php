<?php

namespace App\Providers;

use App\Models\BlacklistClienteFornitore;
use App\Models\ListinoProvvigionale;
use App\Pdf\KycModuleDataProvider;
use Illuminate\Support\ServiceProvider;
use Unico\Core\Models\Cliente;
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
            ->belongsToMany(ListinoProvvigionale::class, ListinoProvvigionale::assegnazioniTable(), 'fornitori_id', 'listino_provvigionale_id')
            ->withPivot('predefinito')
            ->withTimestamps());

        // Divieti istituto-produttore: la tabella è del database dell'app e la chiave è un UUID.
        Fornitore::resolveRelationUsing('blacklistIstituti', fn (Fornitore $fornitore) => $fornitore
            ->hasMany(BlacklistClienteFornitore::class, 'fornitore_id'));
        Cliente::resolveRelationUsing('blacklistProduttori', fn (Cliente $cliente) => $cliente
            ->hasMany(BlacklistClienteFornitore::class, 'cliente_id'));

        // Le tabelle condivise stanno su unico_core_test. Si caricano le migration del
        // pacchetto solo se UNICO_CORE_MIGRATE=true (database proprio o test).
        if (config('database.unico_core_migrate')) {
            $this->loadMigrationsFrom(UnicoCoreServiceProvider::migrationsPath());
        }
    }
}

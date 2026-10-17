<?php

namespace App\Providers;

use App\Documents\Api\LoggedCompanyDataFetcher;
use App\Models\BlacklistClienteFornitore;
use App\Models\ListinoProvvigionale;
use App\Pdf\KycModuleDataProvider;
use App\Services\AiCallRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Google\GoogleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;
use Unico\Core\Documents\Contracts\AiCallLog;
use Unico\Core\Models\Branch;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Document;
use Unico\Core\Models\Fornitore;
use Unico\Core\UnicoCoreServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiCallLog::class, AiCallRegistry::class);
        $this->app->tag(KycModuleDataProvider::class, 'unico-core.module-data-providers');
        $this->app->tag(LoggedCompanyDataFetcher::class, 'unico-core.document-api-fetchers');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Il pannello dei produttori: sui documenti niente esito dell'istruttoria, solleciti né servizi a pagamento.
        config(['unico-core.documents.producer_panels' => ['agenti']]);

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

        // Documenti di istituti e produttori: il core non dichiara la relazione sui due modelli.
        Cliente::resolveRelationUsing('documents', fn (Cliente $cliente) => $cliente
            ->morphMany(Document::class, 'documentable'));
        Fornitore::resolveRelationUsing('documents', fn (Fornitore $fornitore) => $fornitore
            ->morphMany(Document::class, 'documentable'));

        Fornitore::resolveRelationUsing('branches', fn (Fornitore $fornitore) => $fornitore
            ->morphMany(Branch::class, 'branchable'));

        // Le tabelle condivise stanno su unico_core_test. Si caricano le migration del
        // pacchetto solo se UNICO_CORE_MIGRATE=true (database proprio o test).
        if (config('database.unico_core_migrate')) {
            $this->loadMigrationsFrom(UnicoCoreServiceProvider::migrationsPath());
        }

        Event::listen(SocialiteWasCalled::class, [MicrosoftExtendSocialite::class, 'handle']);
        Event::listen(SocialiteWasCalled::class, [GoogleExtendSocialite::class, 'handle']);
    }
}

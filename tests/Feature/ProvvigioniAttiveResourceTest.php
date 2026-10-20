<?php

namespace Tests\Feature;

use App\Filament\Resources\Istituti\Pages\EditIstituto;
use App\Filament\Resources\Istituti\RelationManagers\ProvvigioniAttiveRelationManager;
use App\Filament\Resources\ProvvigioniAttive\Pages\CreateProvvigioneAttiva;
use App\Filament\Resources\ProvvigioniAttive\Pages\ListProvvigioniAttive;
use App\Jobs\ImportaDaMediafacile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\ProvvigioniRule;
use Unico\Core\Models\TipoProdotto;

class ProvvigioniAttiveResourceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    /** @return array{0: ProvvigioniRule, 1: ProvvigioniRule, 2: ProvvigioniRule, 3: Cliente, 4: Cliente} */
    private function regole(): array
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $prestito = TipoProdotto::query()->firstOrCreate(['name' => 'Prestito'], ['tipo_prodotto' => 'Prestito']);
        $mutuo = TipoProdotto::query()->firstOrCreate(['name' => 'Mutuo'], ['tipo_prodotto' => 'Mutuo']);
        $prima = Cliente::query()->create(['name' => 'Banca Prima']);
        $seconda = Cliente::query()->create(['name' => 'Banca Seconda']);

        return [
            ProvvigioniRule::query()->create(['tipoprodotto_id' => (string) $prestito->getKey(), 'clienti_id' => (string) $prima->getKey(), 'tipo_provvigioni' => 'interessi', 'value' => 35]),
            ProvvigioniRule::query()->create(['tipoprodotto_id' => (string) $mutuo->getKey(), 'clienti_id' => (string) $prima->getKey(), 'tipo_provvigioni' => 'erogato', 'value' => 1.4, 'ltv_min' => 50, 'ltv_max' => 70]),
            ProvvigioniRule::query()->create(['tipoprodotto_id' => (string) $prestito->getKey(), 'clienti_id' => (string) $seconda->getKey(), 'tipo_provvigioni' => 'interessi', 'value' => 40]),
            $prima,
            $seconda,
        ];
    }

    public function test_the_list_can_be_filtered_by_product_and_bank(): void
    {
        [$prestitoPrima, $mutuoPrima, $prestitoSeconda, $prima] = $this->regole();
        // Una regola per agente è un'altra cosa e non compare.
        $perAgente = ProvvigioniRule::query()->create(['tipoprodotto_id' => $prestitoPrima->tipoprodotto_id, 'clienti_id' => (string) $prima->getKey(), 'fornitori_id' => '999', 'tipo_provvigioni' => 'lordo', 'value' => 50]);

        // Il database di sviluppo ha già le regole del seeder: si parte dalla banca di prova.
        Livewire::test(ListProvvigioniAttive::class)
            ->filterTable('clienti_id', $prestitoPrima->clienti_id)
            ->assertCanSeeTableRecords([$prestitoPrima, $mutuoPrima])
            ->assertCanNotSeeTableRecords([$prestitoSeconda, $perAgente])
            ->filterTable('tipoprodotto_id', $mutuoPrima->tipoprodotto_id)
            ->assertCanSeeTableRecords([$mutuoPrima])
            ->assertCanNotSeeTableRecords([$prestitoPrima, $prestitoSeconda])
            ->resetTableFilters()
            ->filterTable('clienti_id', $prestitoSeconda->clienti_id)
            ->filterTable('tipoprodotto_id', $prestitoSeconda->tipoprodotto_id)
            ->assertCanSeeTableRecords([$prestitoSeconda])
            ->assertCanNotSeeTableRecords([$prestitoPrima]);
    }

    public function test_the_table_shows_value_base_and_ltv_band(): void
    {
        [$prestito, $mutuo] = $this->regole();
        $libera = ProvvigioniRule::query()->create(['tipoprodotto_id' => $prestito->tipoprodotto_id, 'clienti_id' => $prestito->clienti_id, 'tipo_provvigioni' => 'lordo', 'value' => null]);

        Livewire::test(ListProvvigioniAttive::class)
            ->filterTable('clienti_id', $prestito->clienti_id)
            ->assertTableColumnStateSet('value', '35%', $prestito)
            ->assertTableColumnStateSet('value', '1,4%', $mutuo)
            ->assertTableColumnStateSet('value', 'Libera', $libera)
            ->assertTableColumnStateSet('fascia_ltv', '50–70%', $mutuo);
    }

    public function test_a_rule_can_be_created_from_the_menu_page(): void
    {
        [, , , $prima] = $this->regole();
        $prodotto = TipoProdotto::query()->where('name', 'Mutuo')->firstOrFail();

        Livewire::test(CreateProvvigioneAttiva::class)
            ->fillForm(['tipoprodotto_id' => $prodotto->getKey(), 'clienti_id' => $prima->getKey(), 'tipo_provvigioni' => 'erogato', 'value' => 1.1, 'ltv_min' => 70, 'ltv_max' => 80])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(ProvvigioniRule::query()->where('clienti_id', (string) $prima->getKey())->where('ltv_min', 70)->whereNull('fornitori_id')->exists());
    }

    public function test_the_bank_page_lists_only_its_own_rules(): void
    {
        [$prestitoPrima, $mutuoPrima, $prestitoSeconda, $prima] = $this->regole();

        Livewire::test(ProvvigioniAttiveRelationManager::class, ['ownerRecord' => $prima, 'pageClass' => EditIstituto::class])
            ->assertCanSeeTableRecords([$prestitoPrima, $mutuoPrima])
            ->assertCanNotSeeTableRecords([$prestitoSeconda])
            ->assertTableColumnHidden('clienti.name');
    }

    public function test_the_import_action_queues_the_sixty_day_import(): void
    {
        $this->regole();
        Queue::fake();

        Livewire::test(ListProvvigioniAttive::class)
            ->callTableAction('importaMediafacile')
            ->assertNotified('Importazione avviata');

        Queue::assertPushed(ImportaDaMediafacile::class, fn (ImportaDaMediafacile $job): bool => $job->userId === auth()->id());
    }

    public function test_the_import_job_notifies_the_user_with_new_and_changed_rows(): void
    {
        if (! Schema::connection('proforma')->hasTable('provvigioni')) {
            $this->markTestSkipped('Le tabelle dei compensi sono di Proforma: servono nel database di prova.');
        }

        $this->regole();
        config(['services.mediafacile.base_url' => 'https://mediafacile.test/ws', 'services.mediafacile.header_key' => 'chiave']);
        $pratiche = "ID Pratica\tDenominazione Agente\tDenominazione Banca\tPratica_terzi\nQT91001\tAGENTE AZIONE\tBANCA AZIONE\tNO";
        $compensi = "ID Compenso\tData Inserimento\tDescrizione\tTipo\tImporto\tImporto Effettivo\tStato\tData Pagamento\tN. Fattura\tData Fattura\tData Status\tStatus Compenso\tDenominazione Riferimento\tEntrata Uscita\tID Pratica\tAgente\tIstituto finanziario\tPartita IVA Agente\tCodice Fiscale Agente\tANNULLATA";
        Http::fake(fn (Request $request) => Http::response(str_contains($request->url(), 'table=compensi') ? $compensi : $pratiche));

        ImportaDaMediafacile::dispatchSync(auth()->id());

        $notifica = auth()->user()->notifications()->first();

        // Le tabelle dei compensi sono di Proforma e nel database di prova non ci sono: l'esito dei compensi qui non conta.
        $this->assertStringContainsString('Pratiche: 1 nuove, 0 modificate', $notifica->data['body']);
        $this->assertDatabaseHas('pratiches', ['codice_pratica' => 'QT91001']);
    }
}

<?php

namespace Tests\Feature;

use App\Filament\RelationManagers\CointestatariGarantiRelationManager;
use App\Filament\RelationManagers\ImmobileRelationManager;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\Client;
use App\Models\Pratica;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Address;
use Unico\Core\Models\PraticaClient;
use Unico\Core\Models\PraticaImmobile;
use Unico\Core\Models\TipoProdotto;

class PraticaMutuoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function pratica(string $prodotto): Pratica
    {
        $tipo = TipoProdotto::query()->firstOrCreate(['name' => $prodotto]);

        return Pratica::query()->create(['codice_pratica' => 'MUT-'.$prodotto, 'cognome_cliente' => 'Rossi', 'tipoprodotto_id' => $tipo->getKey(), 'upload_at' => now()]);
    }

    public function test_the_mortgage_relation_managers_show_only_for_a_mutuo(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $mutuo = $this->pratica('Mutuo');
        $cessione = $this->pratica('Cessione');

        foreach ([CointestatariGarantiRelationManager::class, ImmobileRelationManager::class] as $manager) {
            $this->assertTrue($manager::canViewForRecord(Pratica::find($mutuo->getKey()), EditPratica::class));
            $this->assertFalse($manager::canViewForRecord(Pratica::find($cessione->getKey()), EditPratica::class));
        }
    }

    public function test_the_renovation_mortgage_is_a_mutuo_with_works_data(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $pratica = Pratica::find($this->pratica('Mutuo ristrutturazione')->getKey());

        $this->assertTrue(ImmobileRelationManager::canViewForRecord($pratica, EditPratica::class));

        Livewire::test(ImmobileRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class])
            ->callTableAction('create', data: ['finalita' => 'ristrutturazione', 'importo_lavori' => 45000, 'street' => 'Via Lavori'])
            ->assertHasNoTableActionErrors();

        $this->assertEquals(45000, PraticaImmobile::query()->where('pratica_id', $pratica->getKey())->value('importo_lavori'));
    }

    public function test_co_applicants_and_guarantors_are_listed_without_the_applicant(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $pratica = Pratica::find($this->pratica('Mutuo')->getKey());
        $coniuge = Client::query()->create(['name' => 'Bianchi', 'first_name' => 'Anna', 'tax_code' => 'BNCNNA82C45H501Y', 'is_person' => true]);
        $richiedente = Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);
        $garante = PraticaClient::query()->create(['pratica_id' => $pratica->getKey(), 'client_id' => $coniuge->getKey(), 'role' => 'garante']);
        $principale = PraticaClient::query()->create(['pratica_id' => $pratica->getKey(), 'client_id' => $richiedente->getKey(), 'role' => 'richiedente']);

        Livewire::test(CointestatariGarantiRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class])
            ->assertCanSeeTableRecords([$garante])
            ->assertCanNotSeeTableRecords([$principale])
            ->callTableAction('create', data: ['cognome' => 'Rossi', 'codice_fiscale' => 'RSSMRA80A01H501U', 'role' => 'coobbligato', 'share_percent' => 50])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, PraticaClient::query()->where('pratica_id', $pratica->getKey())->where('role', 'coobbligato')->count());
        $this->assertEquals(50, PraticaClient::query()->where('role', 'coobbligato')->value('share_percent'));
    }

    public function test_an_unknown_tax_code_creates_the_client_and_an_invalid_one_is_refused(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $pratica = Pratica::find($this->pratica('Mutuo')->getKey());

        Livewire::test(CointestatariGarantiRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class])
            ->callTableAction('create', data: ['cognome' => 'Verdi', 'nome' => 'Luigi', 'codice_fiscale' => 'NONVALIDO', 'role' => 'garante'])
            ->assertHasTableActionErrors(['codice_fiscale']);

        Livewire::test(CointestatariGarantiRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class])
            ->callTableAction('create', data: ['cognome' => 'Bianchi', 'nome' => 'Anna', 'codice_fiscale' => 'bncnna82c45h501y', 'role' => 'garante'])
            ->assertHasNoTableActionErrors();

        $client = Client::query()->where('tax_code', 'BNCNNA82C45H501Y')->firstOrFail();
        $this->assertSame('Anna', $client->first_name);
        $this->assertTrue($client->is_person);
        $this->assertSame(1, PraticaClient::query()->where('client_id', $client->getKey())->count());
    }

    public function test_the_property_is_saved_with_a_localita_address_and_ltv(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $pratica = Pratica::find($this->pratica('Mutuo')->getKey());
        $pratica->update(['amount' => 80000]);

        Livewire::test(ImmobileRelationManager::class, ['ownerRecord' => $pratica->fresh(), 'pageClass' => EditPratica::class])
            ->callTableAction('create', data: ['tipologia' => 'abitazione', 'valore_stimato' => 160000, 'street' => 'Via Roma', 'numero' => '10', 'city' => 'Napoli', 'zip_code' => '80100', 'province' => 'NA'])
            ->assertHasNoTableActionErrors();

        $immobile = PraticaImmobile::query()->where('pratica_id', $pratica->getKey())->firstOrFail();
        $this->assertSame('Località', $immobile->address->addressType->name);
        $this->assertSame('pratica_immobile', $immobile->address->addressable_type);
        $this->assertSame('Via Roma', $immobile->address->street);
        $this->assertSame(50.0, $immobile->ltv(80000));

        Livewire::test(ImmobileRelationManager::class, ['ownerRecord' => $pratica->fresh(), 'pageClass' => EditPratica::class])
            ->assertCanSeeTableRecords([$immobile])
            ->callTableAction('edit', $immobile, data: ['street' => 'Via Milano', 'valore_stimato' => 200000])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Via Milano', $immobile->fresh()->address->street);
        $this->assertSame(1, Address::query()->where('addressable_type', 'pratica_immobile')->count());
    }
}

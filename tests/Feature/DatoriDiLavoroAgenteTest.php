<?php

namespace Tests\Feature;

use App\Filament\Agenti\Resources\DatoriDiLavoro\Pages\ListDatoriDiLavoro;
use App\Filament\Agenti\Resources\DatoriDiLavoro\Pages\ViewDatoreDiLavoro;
use App\Filament\Agenti\Resources\DatoriDiLavoro\RelationManagers\DipendentiRelationManager;
use App\Models\Client;
use App\Models\ClientRelation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;

class DatoriDiLavoroAgenteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_the_producer_sees_only_the_employers_of_his_own_clients_and_only_those_employees(): void
    {
        Filament::setCurrentPanel('agenti');
        $utente = User::factory()->create(['role' => 'fornitore']);
        $this->actingAs($utente);
        $mio = Fornitore::query()->create(['name' => 'Produttore Mio', 'piva' => '9'.random_int(1000000000, 9999999999), 'user_id' => $utente->id, 'is_active' => true]);
        $altro = Fornitore::query()->create(['name' => 'Produttore Altro', 'piva' => '9'.random_int(1000000000, 9999999999), 'is_active' => true]);
        $marker = 'ZZ'.random_int(1000, 9999);

        $comune = Client::query()->create(['name' => $marker.' Comune', 'tax_code' => '1'.random_int(1000000000, 9999999999), 'is_person' => false]);
        $ministero = Client::query()->create(['name' => $marker.' Ministero', 'tax_code' => '2'.random_int(1000000000, 9999999999), 'is_person' => false]);
        $suo = Client::query()->create(['name' => $marker.' Rossi', 'tax_code' => 'RSSMRA80A01H501'.chr(random_int(65, 90)), 'is_person' => true, 'employer_id' => $comune->getKey()]);
        $collega = Client::query()->create(['name' => $marker.' Verdi', 'tax_code' => 'VRDLGU80A01H501'.chr(random_int(65, 90)), 'is_person' => true, 'employer_id' => $comune->getKey()]);
        $altrui = Client::query()->create(['name' => $marker.' Bianchi', 'tax_code' => 'BNCMRA80A01H501'.chr(random_int(65, 90)), 'is_person' => true, 'employer_id' => $ministero->getKey()]);
        Pratica::query()->create(['codice_pratica' => $marker.'-1', 'codice_fiscale' => $suo->tax_code, 'fornitori_id' => $mio->getKey(), 'upload_at' => now()]);
        Pratica::query()->create(['codice_pratica' => $marker.'-2', 'codice_fiscale' => $collega->tax_code, 'fornitori_id' => $altro->getKey(), 'upload_at' => now()]);
        Pratica::query()->create(['codice_pratica' => $marker.'-3', 'codice_fiscale' => $altrui->tax_code, 'fornitori_id' => $altro->getKey(), 'upload_at' => now()]);
        $this->assertSame(3, ClientRelation::query()->where('role', 'dipendente')->whereIn('client_id', [$suo->getKey(), $collega->getKey(), $altrui->getKey()])->count());

        Livewire::test(ListDatoriDiLavoro::class)
            ->searchTable($marker)
            ->assertCanSeeTableRecords([$comune])
            ->assertCanNotSeeTableRecords([$ministero]);

        Livewire::test(ViewDatoreDiLavoro::class, ['record' => $comune->getKey()])->assertSuccessful();

        Livewire::test(DipendentiRelationManager::class, ['ownerRecord' => $comune, 'pageClass' => ViewDatoreDiLavoro::class])
            ->assertSee($marker.' Rossi')
            ->assertDontSee($marker.' Verdi');
    }
}

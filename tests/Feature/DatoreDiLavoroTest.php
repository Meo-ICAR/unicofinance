<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\DatoriDiLavoro\Pages\EditDatoreDiLavoro;
use App\Filament\Resources\DatoriDiLavoro\Pages\ListDatoriDiLavoro;
use App\Models\Client;
use App\Models\ClientRelation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DatoreDiLavoroTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function admin(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
    }

    public function test_the_employer_is_linked_to_the_employee_with_the_dipendente_role(): void
    {
        $datore = Client::query()->create(['name' => 'Comune di Prova', 'tax_code' => '12345678903', 'is_person' => false]);
        $altro = Client::query()->create(['name' => 'Altro Ente', 'tax_code' => '98765432109', 'is_person' => false]);
        $persona = Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true, 'employer_id' => $datore->getKey()]);

        $this->assertTrue(ClientRelation::query()->where(['company_id' => $datore->getKey(), 'client_id' => $persona->getKey(), 'role' => 'dipendente'])->exists());
        $this->assertTrue($datore->isDatoreDiLavoro());

        $persona->update(['employer_id' => $altro->getKey()]);

        $this->assertFalse(ClientRelation::query()->where(['company_id' => $datore->getKey(), 'role' => 'dipendente'])->exists());
        $this->assertTrue(ClientRelation::query()->where(['company_id' => $altro->getKey(), 'client_id' => $persona->getKey(), 'role' => 'dipendente'])->exists());

        $persona->update(['employer_id' => null]);

        $this->assertFalse($altro->refresh()->isDatoreDiLavoro() && ClientRelation::query()->where('role', 'dipendente')->where('client_id', $persona->getKey())->exists());
    }

    public function test_the_employer_is_the_client_with_at_least_one_person_as_dipendente_in_the_relations(): void
    {
        $datore = Client::query()->create(['name' => 'Comune di Prova', 'tax_code' => '12345678903', 'is_person' => false]);
        $azienda = Client::query()->create(['name' => 'Azienda Cliente', 'tax_code' => '98765432109', 'is_person' => false]);
        $persona = Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);
        $socio = Client::query()->create(['name' => 'Bianchi', 'tax_code' => 'BNCMRA80A01H501U', 'is_person' => true]);
        ClientRelation::query()->create(['company_id' => $datore->getKey(), 'client_id' => $persona->getKey(), 'role' => ClientRelation::RUOLO_DIPENDENTE]);
        ClientRelation::query()->create(['company_id' => $azienda->getKey(), 'client_id' => $socio->getKey(), 'role' => 'socio']);

        $this->assertTrue($datore->isDatoreDiLavoro());
        $this->assertFalse($azienda->isDatoreDiLavoro());
        $datori = Client::query()->datoriDiLavoro()->pluck('id')->all();
        $altri = Client::query()->senzaDatoriDiLavoro()->pluck('id')->all();
        $this->assertContains($datore->getKey(), $datori);
        $this->assertNotContains($azienda->getKey(), $datori);
        $this->assertNotContains($datore->getKey(), $altri);
        $this->assertContains($azienda->getKey(), $altri);
        $this->assertContains($persona->getKey(), $altri);
    }

    public function test_employers_have_their_own_list_and_are_hidden_from_the_clients_unless_asked(): void
    {
        $this->admin();
        $marker = 'ZZ-'.uniqid();
        $datore = Client::query()->create(['name' => $marker.' Comune', 'tax_code' => '12345678903', 'is_person' => false]);
        $azienda = Client::query()->create(['name' => $marker.' Azienda', 'tax_code' => '98765432109', 'is_person' => false]);
        Client::query()->create(['name' => $marker.' Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true, 'employer_id' => $datore->getKey()]);

        Livewire::test(ListDatoriDiLavoro::class)
            ->searchTable($marker)
            ->assertCanSeeTableRecords([$datore])
            ->assertCanNotSeeTableRecords([$azienda]);

        Livewire::test(ListClients::class)
            ->searchTable($marker)
            ->assertCanSeeTableRecords([$azienda])
            ->assertCanNotSeeTableRecords([$datore])
            ->filterTable('con_datori')
            ->assertCanSeeTableRecords([$azienda, $datore]);

        Livewire::test(EditDatoreDiLavoro::class, ['record' => $datore->getKey()])
            ->assertSuccessful()
            ->assertSee('Organigramma')
            ->assertDontSee('Soci e titolari effettivi');
    }

    public function test_an_employer_shows_typology_and_benestare_but_not_company_data_nor_verifications(): void
    {
        $this->admin();
        $datore = Client::query()->create(['name' => 'Comune di Prova', 'tax_code' => '12345678903', 'is_person' => false]);
        Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true, 'employer_id' => $datore->getKey()]);

        Livewire::test(EditClient::class, ['record' => $datore->getKey()])
            ->assertSee('Dati societari')
            ->assertFormFieldExists('employment_typology')
            ->assertFormFieldExists('richiesta_benestare')
            ->assertFormFieldExists('ritiro_benestare')
            ->assertFormFieldExists('notifica')
            ->assertFormFieldHidden('legal_form')
            ->assertFormFieldHidden('ateco_code')
            ->assertFormFieldHidden('balance_year')
            ->assertDontSee('Verifiche');
    }

    public function test_a_plain_company_keeps_its_company_data_and_verifications(): void
    {
        $this->admin();
        $societa = Client::query()->create(['name' => 'Acme Srl', 'tax_code' => '12345678903', 'is_person' => false]);

        Livewire::test(EditClient::class, ['record' => $societa->getKey()])
            ->assertFormFieldVisible('legal_form')
            ->assertFormFieldVisible('ateco_code')
            ->assertSee('Verifiche');
    }

    public function test_the_employer_field_is_only_available_with_a_cessione_or_delega(): void
    {
        $this->admin();
        $persona = Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->assertFormFieldHidden('employer_id');
    }
}

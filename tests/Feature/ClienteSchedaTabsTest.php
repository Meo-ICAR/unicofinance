<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\TipoProdotto;

class ClienteSchedaTabsTest extends TestCase
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

    public function test_info_tab_holds_privacy_lead_and_user_and_privacy_is_for_individuals_only(): void
    {
        $this->admin();
        $persona = Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);
        $societa = Client::query()->create(['name' => 'Acme Srl', 'tax_code' => '12345678903', 'is_person' => false]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->assertSee('Info')
            ->assertSee('Consensi privacy')
            ->assertFormFieldExists('general_consent_at')
            ->assertFormFieldExists('is_lead')
            ->assertSee('Utente collegato');

        Livewire::test(EditClient::class, ['record' => $societa->getKey()])
            ->assertDontSee('Consensi privacy')
            ->assertSee('Utente collegato');
    }

    public function test_the_user_is_created_and_linked_for_an_individual_with_an_email(): void
    {
        $this->admin();
        $persona = Client::query()->create(['name' => 'Rossi', 'first_name' => 'Mario', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true, 'email' => 'Mario.Rossi@example.com']);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->callAction(TestAction::make('creaUtente')->schemaComponent('user_id'))
            ->assertNotified();

        $utente = User::query()->where('email', 'mario.rossi@example.com')->firstOrFail();
        $this->assertSame($utente->getKey(), $persona->refresh()->user_id);
        $this->assertSame('client', $utente->role);
        $this->assertSame('Mario Rossi', $utente->name);
    }

    public function test_the_user_is_not_created_without_an_email(): void
    {
        $this->admin();
        $persona = Client::query()->create(['name' => 'Verdi', 'tax_code' => 'VRDLGI80A01H501U', 'is_person' => true]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->callAction(TestAction::make('creaUtente')->schemaComponent('user_id'));

        $this->assertNull($persona->refresh()->user_id);
    }

    public function test_the_kyc_module_needs_a_practice_to_be_printed(): void
    {
        $this->admin();
        $persona = Client::query()->create(['name' => 'Bianchi', 'tax_code' => 'BNCLRA80A01H501U', 'is_person' => true]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->callAction(TestAction::make('stampaModuloKyc')->schemaComponent('kyc'))
            ->assertNotified();
    }

    public function test_the_client_list_search_does_not_touch_tables_missing_from_this_app(): void
    {
        $this->admin();
        Client::query()->create(['name' => 'Hassisto Srl', 'tax_code' => '12345678903', 'is_person' => false]);

        Livewire::test(ListClients::class)
            ->searchTable('hass')
            ->assertCanSeeTableRecords(Client::query()->where('name', 'like', '%hass%')->get());
    }

    public function test_kyc_financing_nature_is_prefilled_from_the_last_practice_and_shows_the_employer_for_salary_products(): void
    {
        $this->admin();
        $persona = Client::query()->create(['name' => 'Gialli', 'tax_code' => 'GLLMRA80A01H501U', 'is_person' => true]);
        $datore = Client::query()->create(['name' => 'Comune di Prova', 'tax_code' => '12345678903', 'is_person' => false]);
        $persona->update(['employer_id' => $datore->getKey()]);
        Pratica::query()->create(['codice_pratica' => 'KYC-1', 'codice_fiscale' => $persona->tax_code, 'tipoprodotto_id' => TipoProdotto::query()->where('name', 'Delega')->value('id'), 'data_inserimento_pratica' => now(), 'upload_at' => now()]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->assertSet('data.questionarioKyc.financing_nature', 'cessione_quinto')
            ->assertSee('Comune di Prova');
    }

    public function test_the_employer_is_not_shown_in_the_kyc_for_other_products(): void
    {
        $this->admin();
        $persona = Client::query()->create(['name' => 'Neri', 'tax_code' => 'NRELGI80A01H501U', 'is_person' => true]);
        Pratica::query()->create(['codice_pratica' => 'KYC-2', 'codice_fiscale' => $persona->tax_code, 'tipoprodotto_id' => TipoProdotto::query()->where('name', 'Mutuo')->value('id'), 'data_inserimento_pratica' => now(), 'upload_at' => now()]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->assertSet('data.questionarioKyc.financing_nature', 'mutuo')
            ->assertDontSee('si imposta in');
    }
}

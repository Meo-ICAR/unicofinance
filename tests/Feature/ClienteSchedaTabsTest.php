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

    public function test_privacy_has_its_own_tab_for_individuals_only_and_is_out_of_the_verifications(): void
    {
        $this->admin();
        $persona = Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);
        $societa = Client::query()->create(['name' => 'Acme Srl', 'tax_code' => '12345678903', 'is_person' => false]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->assertSee('Privacy')
            ->assertFormFieldExists('general_consent_at')
            ->assertFormFieldExists('is_lead')
            ->assertSee('Utente collegato');

        Livewire::test(EditClient::class, ['record' => $societa->getKey()])
            ->assertDontSee('Consensi privacy');
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
}

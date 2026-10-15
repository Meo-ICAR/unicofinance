<?php

namespace Tests\Feature;

use App\Enums\ApiProvider;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\ApiCall;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClienteCreditScoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_the_credit_score_request_is_simulated_and_notified(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $client = Client::query()->create(['name' => 'Rossi', 'first_name' => 'Mario', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertFormFieldIsVisible('first_name')
            ->callAction(TestAction::make('creditScore')->schemaComponent('tax_code', 'form'))
            ->assertNotified('Credit score: richiesta attivata');

        $this->assertTrue(ApiCall::query()->where('provider', ApiProvider::OpenapiCreditScore)->exists());
    }

    public function test_an_invalid_tax_code_is_rejected_on_save(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $client = Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->fillForm(['tax_code' => 'NONVALIDO'])
            ->call('save')
            ->assertHasFormErrors(['tax_code']);
    }

    public function test_a_company_hides_the_first_name(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $client = Client::query()->create(['name' => 'Acme Srl', 'tax_code' => '12345678903', 'is_person' => false]);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertFormFieldIsHidden('first_name');
    }
}

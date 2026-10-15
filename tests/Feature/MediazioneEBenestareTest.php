<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\Client;
use App\Models\Pratica;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MediazioneEBenestareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
    }

    public function test_the_consent_fields_appear_only_on_the_card_of_an_employer(): void
    {
        $datore = Client::query()->create(['name' => 'Ente Datore', 'is_person' => false]);
        $societa = Client::query()->create(['name' => 'Acme Srl', 'is_person' => false]);
        $persona = Client::query()->create(['name' => 'Rossi', 'first_name' => 'Mario', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true, 'employer_id' => $datore->getKey()]);

        Livewire::test(EditClient::class, ['record' => $datore->getKey()])
            ->assertFormFieldVisible('richiesta_benestare')
            ->assertFormFieldVisible('ritiro_benestare')
            ->assertFormFieldVisible('notifica');

        Livewire::test(EditClient::class, ['record' => $societa->getKey()])
            ->assertFormFieldHidden('richiesta_benestare');

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->assertFormFieldHidden('richiesta_benestare');
    }

    public function test_the_practice_shows_the_mediation_amount_asked_to_the_client(): void
    {
        $pratica = Pratica::query()->create(['codice_pratica' => 'MED-1', 'cognome_cliente' => 'Rossi', 'amount' => 10000, 'mediation_fee_percent' => 3, 'upload_at' => now()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertSee('Mediazione richiesta al cliente')
            ->assertSee('€ 300,00')
            ->fillForm(['mediation_fee_amount' => 450])
            ->assertSee('€ 450,00');
    }
}

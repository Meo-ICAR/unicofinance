<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Cliente;

class BancaPreferitaClienteTest extends TestCase
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

    public function test_si_sceglie_la_banca_preferita_tra_quelle_attive_e_si_salva(): void
    {
        $attiva = Cliente::query()->create(['name' => 'Banca Attiva', 'is_active' => true]);
        $spenta = Cliente::query()->create(['name' => 'Banca Spenta', 'is_active' => false]);
        $persona = Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->assertFormFieldExists('preferred_bank_id', fn ($field): bool => $field->getLabel() === 'Banca preferita nuovi finanziamenti'
                && array_key_exists($attiva->getKey(), $field->getOptions())
                && ! array_key_exists($spenta->getKey(), $field->getOptions()))
            ->fillForm(['preferred_bank_id' => $attiva->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($attiva->getKey(), $persona->fresh()->preferred_bank_id);
        $this->assertSame('Banca Attiva', $persona->fresh()->preferredBank->name);
    }

    public function test_una_banca_non_attiva_non_si_puo_scegliere(): void
    {
        $spenta = Cliente::query()->create(['name' => 'Banca Spenta', 'is_active' => false]);
        $persona = Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->fillForm(['preferred_bank_id' => $spenta->getKey()])
            ->call('save')
            ->assertHasFormErrors(['preferred_bank_id']);

        $this->assertNull($persona->fresh()->preferred_bank_id);
    }
}

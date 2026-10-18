<?php

namespace Tests\Feature;

use App\Filament\RelationManagers\ImpegniTerziRelationManager;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\Client;
use App\Models\Pratica;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\TipoProdotto;

class ImpegniTerziEstinzioneTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function pratica(string $codice, array $attributi = []): Pratica
    {
        return Pratica::query()->create(array_merge(['codice_pratica' => $codice, 'cognome_cliente' => 'Rossi', 'nome_cliente' => 'Mario', 'codice_fiscale' => 'RSSMRA80A01H501U', 'upload_at' => now()], $attributi));
    }

    private function gestore(Pratica $pratica)
    {
        return Livewire::test(ImpegniTerziRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class]);
    }

    public function test_clicking_the_extinguish_icon_toggles_only_between_null_and_the_current_practice(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $corrente = $this->pratica('NEW-1');
        $altra = $this->pratica('NEW-OTHER');
        $impegno = $this->pratica('OLD-1', ['is_notowned' => true]);
        $occupato = $this->pratica('OLD-2', ['is_notowned' => true, 'estingued_by' => $altra->getKey()]);
        $clic = fn (Pratica $riga) => $this->gestore($corrente)->callTableColumnAction('estinzione', (string) $riga->getKey());

        $clic($impegno);
        $this->assertSame($corrente->getKey(), $impegno->fresh()->estingued_by);

        $clic($impegno);
        $this->assertNull($impegno->fresh()->estingued_by);

        $clic($occupato);
        $this->assertSame($altra->getKey(), $occupato->fresh()->estingued_by);
    }

    public function test_a_new_obligation_inherits_the_requester_of_the_practice(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $corrente = $this->pratica('NEW-2');
        $tipo = TipoProdotto::query()->firstOrCreate(['name' => 'Test terzi'], ['tipo_prodotto' => 'test_terzi', 'is_third_party' => true]);
        $istituto = Cliente::query()->create(['name' => 'Finanziaria Spenta', 'is_active' => false]);

        $this->gestore($corrente)
            ->callAction(TestAction::make('create')->table(), ['tipoprodotto_id' => $tipo->getKey(), 'clienti_id' => $istituto->getKey(), 'codice_pratica' => 'IMP-9', 'rata' => 250])
            ->assertHasNoFormErrors();

        $impegno = Pratica::query()->where('codice_pratica', 'IMP-9')->firstOrFail();
        $this->assertTrue($impegno->is_notowned);
        $this->assertSame('RSSMRA80A01H501U', $impegno->codice_fiscale);
        $this->assertSame('Rossi', $impegno->cognome_cliente);
        $this->assertSame('Finanziaria Spenta', $impegno->denominazione_banca);
    }

    public function test_the_requester_fields_are_read_only_once_the_customer_has_a_record(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $pratica = $this->pratica('NEW-3');
        $senzaScheda = $this->pratica('NEW-4', ['codice_fiscale' => 'VRDGPP75B02F205X']);
        Client::query()->create(['name' => 'Rossi', 'first_name' => 'Mario', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertFormFieldIsReadOnly('cognome_cliente')
            ->assertFormFieldIsReadOnly('codice_fiscale');

        Livewire::test(EditPratica::class, ['record' => $senzaScheda->getKey()])
            ->assertFormFieldExists('cognome_cliente', fn ($campo): bool => ! $campo->isReadOnly());
    }
}

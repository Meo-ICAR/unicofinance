<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\FinancingQuote;
use App\Models\Pratica;
use App\Models\User;
use App\Services\Istruttoria\DatoreDiLavoro;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\TipoProdotto;

class DatoreDiLavoroTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    /** @return array{0: Client, 1: Pratica} */
    private function clienteConCessione(string $prodotto = 'Cessione', string $tipo = 'statale'): array
    {
        $cliente = Client::query()->create(['name' => 'Rossi', 'first_name' => 'Mario', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);
        $tipoProdotto = TipoProdotto::query()->firstOrCreate(['name' => $prodotto], ['tipo_prodotto' => $prodotto]);
        $pratica = Pratica::query()->create(['codice_pratica' => 'DDL-'.uniqid(), 'codice_fiscale' => $cliente->tax_code, 'tipoprodotto_id' => $tipoProdotto->getKey(), 'data_inserimento_pratica' => now()->toDateString(), 'upload_at' => now()]);
        $fornitore = Fornitore::query()->create(['name' => 'Agente DDL', 'piva' => '9'.random_int(1000000000, 9999999999), 'is_active' => true]);
        FinancingQuote::query()->create([
            'fornitore_id' => $fornitore->getKey(), 'number' => 1, 'contract_type' => 'cessione', 'sex' => 'M', 'birth_date' => '1980-01-01', 'employment_relationship' => $tipo,
            'hired_at' => '2010-01-01', 'starts_at' => now()->toDateString(), 'duration_months' => 120, 'assignable_quota' => 300, 'age_at_start' => 46, 'seniority_months' => 200,
            'status' => 'salvato', 'pratica_id' => $pratica->getKey(),
        ]);

        return [$cliente, $pratica];
    }

    public function test_the_employer_is_required_only_for_cessione_and_delega(): void
    {
        [$cessione] = $this->clienteConCessione('Cessione');
        $servizio = app(DatoreDiLavoro::class);

        $this->assertTrue($servizio->richiesto($cessione));
        $this->assertSame('Statale', $servizio->tipoAmministrazione($cessione));

        $mutuo = Client::query()->create(['name' => 'Bianchi', 'tax_code' => 'BNCNNA82C45H501Y', 'is_person' => true]);
        $tipo = TipoProdotto::query()->firstOrCreate(['name' => 'Mutuo'], ['tipo_prodotto' => 'Mutuo']);
        Pratica::query()->create(['codice_pratica' => 'DDL-M', 'codice_fiscale' => $mutuo->tax_code, 'tipoprodotto_id' => $tipo->getKey(), 'upload_at' => now()]);

        $this->assertFalse($servizio->richiesto($mutuo));
    }

    public function test_a_new_employer_inherits_the_administration_type_of_the_cessione_practice(): void
    {
        [$cliente] = $this->clienteConCessione('Cessione', 'privato_spa');

        $datore = app(DatoreDiLavoro::class)->crea($cliente, ['name' => 'Acme Spa', 'tax_code' => '12345678903']);

        $this->assertFalse($datore->is_person);
        $this->assertSame('12345678903', $datore->tax_code);
        $this->assertSame('Privato SPA', $datore->employment_typology);
    }

    public function test_saving_the_client_makes_an_existing_employer_inherit_the_type_only_when_it_has_none(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        [$cliente] = $this->clienteConCessione('Delega', 'pubblico');
        $senzaTipo = Client::query()->create(['name' => 'Ente Uno', 'is_person' => false]);
        $conTipo = Client::query()->create(['name' => 'Ente Due', 'is_person' => false, 'employment_typology' => 'Statale']);

        Livewire::test(EditClient::class, ['record' => $cliente->getKey()])
            ->assertFormFieldVisible('employer_id')
            ->fillForm(['employer_id' => $senzaTipo->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Pubblico', $senzaTipo->fresh()->employment_typology);

        Livewire::test(EditClient::class, ['record' => $cliente->getKey()])
            ->fillForm(['employer_id' => $conTipo->getKey()])
            ->call('save');

        $this->assertSame('Statale', $conTipo->fresh()->employment_typology);
    }
}

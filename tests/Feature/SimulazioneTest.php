<?php

namespace Tests\Feature;

use App\Filament\Agenti\Pages\Simulazione;
use App\Models\FinancingQuote;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;

class SimulazioneTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private const XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Offerte>
  <Offerta><Importo_erogato>12.345,67</Importo_erogato><Errore>2</Errore></Offerta>
  <Offerta><Importo_erogato>10000.50</Importo_erogato><Errore>2</Errore></Offerta>
  <Offerta><Importo_erogato>1</Importo_erogato><Errore>1</Errore></Offerta>
</Offerte>
XML;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'preventivatore.url' => 'https://preventivatore.test/offerte',
            'preventivatore.passkey' => 'secret',
        ]);

        $user = User::factory()->create([
            'email' => 'agente-'.Str::uuid().'@test.local',
        ]);
        Fornitore::query()->create([
            'name' => 'Agente Simulazione',
            'piva' => '99900000099',
            'user_id' => $user->id,
        ]);

        Filament::setCurrentPanel('agenti');
        $this->actingAs($user);
    }

    public function test_agent_opens_the_simulation_and_saves_a_cession_quote(): void
    {
        Http::fake(['preventivatore.test/*' => Http::response(self::XML)]);

        $pratiche = Pratica::query()->count();

        Livewire::test(Simulazione::class)
            ->fillForm([
                'tipo_contratto' => 'cessione',
                'sesso' => 'F',
                'data_nascita' => '1980-02-20',
                'tipo_rapporto' => 'statale',
                'data_assunzione' => '2015-06-01',
                'durata' => 120,
                'quota_cedibile' => 280,
                'data_decorrenza' => '2027-01-13',
            ])
            ->call('simulate')
            ->assertHasNoFormErrors()
            ->assertSet('esito.min', 10000.5)
            ->assertSet('esito.max', 12345.67)
            ->assertSee('Salva preventivo')
            ->assertSee('Perfeziona')
            ->call('salvaPreventivo')
            ->assertSet('preventivoNumero', 1)
            ->assertSet('codicePratica', null);

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['Passkey'] === 'secret'
                && $query['Tipo_contratto'] === 'Cessione'
                && $query['Tipo_rapporto'] === 'Statale'
                && $query['Importo_rata'] === '280,00'
                && $query['Data_decorrenza'] === '01-13-2027'
                && $query['Durata'] === '120'
                && $query['Sesso'] === 'F';
        });

        $quote = FinancingQuote::query()->first();
        $this->assertNotNull($quote);
        $this->assertSame('salvato', $quote->status);
        $this->assertNull($quote->pratica_id);
        $this->assertSame($pratiche, Pratica::query()->count());
    }

    public function test_finalizing_a_personal_loan_creates_a_perfected_practice(): void
    {
        Http::fake(['preventivatore.test/*' => Http::response(self::XML)]);

        Livewire::test(Simulazione::class)
            ->fillForm([
                'tipo_contratto' => 'prestito',
                'sesso' => 'M',
                'data_nascita' => '1955-03-01',
                'tipo_rapporto' => 'pensionato_inps',
                'durata' => 84,
                'importo_richiesto' => 15000,
                'data_decorrenza' => '2027-01-13',
            ])
            ->assertFormFieldHidden('data_assunzione')
            ->call('simulate')
            ->assertHasNoFormErrors()
            ->call('perfeziona')
            ->assertSet('preventivoNumero', 1);

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['Tipo_contratto'] === 'Prestito'
                && $query['Tipo_rapporto'] === 'Pensionato INPS'
                && $query['Importo_richiesto'] === '15000,00'
                && $query['Durata'] === '84'
                && ! array_key_exists('Data_assunzione', $query)
                && ! array_key_exists('Importo_rata', $query);
        });

        $quote = FinancingQuote::query()->first();
        $this->assertSame('perfezionato', $quote->status);

        $pratica = $quote->pratica;
        $this->assertNotNull($pratica);
        $this->assertSame('PERFEZIONATA', $pratica->praticaStato->codice);
        $this->assertSame('Prestito', $pratica->tipoprodotto->name);
        $this->assertSame('15000.00', $pratica->amount);
        $this->assertStringStartsWith('SIM-', $pratica->codice_pratica);
    }
}

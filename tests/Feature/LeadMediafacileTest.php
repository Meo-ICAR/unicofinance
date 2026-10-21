<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Pratica;
use App\Models\PraticaLead;
use App\Services\Preventivazione\LeadSender;
use App\Services\Preventivazione\QuoteUnavailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadMediafacileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'preventivatore.lead.url' => 'https://mediafacile.test/ws/lead-input-get.php',
            'preventivatore.lead.passkey' => 'PASS',
            'preventivatore.lead.fonte' => 'unicofinance',
        ]);
    }

    private function pratica(): Pratica
    {
        Client::query()->create([
            'name' => 'Rossi', 'first_name' => 'Mario', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true,
            'birth_date' => '1980-01-31', 'employment_typology' => 'Privato SPA', 'phone' => '3331234567', 'email' => 'mario@example.test',
        ]);

        return Pratica::query()->create([
            'codice_pratica' => 'SIM-1', 'codice_fiscale' => 'RSSMRA80A01H501U', 'amount' => 12500.5, 'upload_at' => now(),
        ]);
    }

    public function test_i_parametri_seguono_la_specifica(): void
    {
        $params = app(LeadSender::class)->parameters($this->pratica());

        $this->assertSame('Rossi', $params['cognome']);
        $this->assertSame('Mario', $params['nome']);
        $this->assertSame('01-31-1980', $params['data_nascita']);
        $this->assertSame('Privato', $params['tipologia']);
        $this->assertSame('12500,50', $params['importo_richiesto']);
        $this->assertSame('RSSMRA80A01H501U', $params['codice_fiscale']);
        $this->assertSame('unicofinance', $params['fonte']);
        $this->assertArrayNotHasKey('residenza_provincia', $params);
    }

    public function test_un_lead_ok_si_registra_con_lidu_e_non_si_ritrasmette(): void
    {
        Http::fake(['mediafacile.test/*' => Http::response('<Risposta><Stato>OK</Stato><IDUU>987</IDUU></Risposta>')]);
        $pratica = $this->pratica();
        $sender = app(LeadSender::class);

        $lead = $sender->send($pratica);

        $this->assertTrue($lead->is_ok);
        $this->assertSame('987', $lead->iduu);
        $this->assertSame($lead->id, $sender->alreadySent($pratica)?->id);
        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET' && ($query['Passkey'] ?? null) === 'PASS' && ($query['cognome'] ?? null) === 'Rossi';
        });
    }

    public function test_uno_stato_ko_non_conta_come_trasmesso(): void
    {
        Http::fake(['mediafacile.test/*' => Http::response('<Risposta><Stato>KO - email non valida</Stato></Risposta>')]);
        $pratica = $this->pratica();
        $sender = app(LeadSender::class);

        $lead = $sender->send($pratica);

        $this->assertFalse($lead->is_ok);
        $this->assertSame('KO - email non valida', $lead->stato);
        $this->assertNull($sender->alreadySent($pratica));
        $this->assertSame(1, PraticaLead::count());
    }

    public function test_senza_configurazione_non_si_invia(): void
    {
        config(['preventivatore.lead.url' => null]);
        Http::fake();

        $this->expectException(QuoteUnavailable::class);

        try {
            app(LeadSender::class)->send($this->pratica());
        } finally {
            Http::assertNothingSent();
        }
    }
}

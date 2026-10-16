<?php

namespace Tests\Feature;

use App\Enums\ApiProvider;
use App\Filament\Agenti\Resources\Clients\Pages\EditClient;
use App\Filament\Agenti\Resources\Pratiche\Pages\EditPratica;
use App\Filament\Agenti\Resources\Pratiche\Pages\ListPratiche;
use App\Filament\Agenti\Resources\Pratiche\PraticaResource;
use App\Models\ApiCall;
use App\Models\Client;
use App\Models\KycQuestionnaire;
use App\Models\User;
use App\Services\Agenti\AgentImpersonationService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Document;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\TipoProdotto;

class PraticheAgenteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_the_agents_panel_lists_only_the_impersonated_agents_practices(): void
    {
        Filament::setCurrentPanel('agenti');

        $staff = User::factory()->create(['role' => 'super_admin']);
        $agent = User::factory()->create(['role' => 'fornitore']);
        $otherAgent = User::factory()->create(['role' => 'fornitore']);
        $fornitore = Fornitore::query()->create([
            'name' => 'Produttore impersonato',
            'piva' => '999'.random_int(10000000, 99999999),
            'user_id' => $agent->id,
            'is_active' => true,
        ]);
        $other = Fornitore::query()->create([
            'name' => 'Altro produttore',
            'piva' => '998'.random_int(10000000, 99999999),
            'user_id' => $otherAgent->id,
            'is_active' => true,
        ]);
        $marker = 'ZZPRA'.Str::upper(Str::random(4));
        $own = Pratica::query()->create([
            'codice_pratica' => $marker.'-MIA',
            'cognome_cliente' => 'Rossi',
            'fornitori_id' => $fornitore->getKey(),
            'upload_at' => now(),
        ]);
        $foreign = Pratica::query()->create([
            'codice_pratica' => $marker.'-ALTRA',
            'cognome_cliente' => 'Bianchi',
            'fornitori_id' => $other->getKey(),
            'upload_at' => now(),
        ]);

        $this->actingAs($staff);
        app(AgentImpersonationService::class)->start($staff, $fornitore);

        $this->assertSame((int) $fornitore->getKey(), app(AgentImpersonationService::class)->agentFornitoreId());
        $this->assertSame($agent->id, auth()->id());

        $this->actingAs($agent);

        Livewire::test(ListPratiche::class)
            ->searchTable($marker)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign])
            ->assertActionHasLabel(TestAction::make('edit')->table($own), 'Perfeziona')
            ->assertActionHasUrl(
                TestAction::make('edit')->table($own),
                PraticaResource::getUrl('edit', ['record' => $own], panel: 'agenti'),
            );

        Livewire::test(EditPratica::class, ['record' => $foreign->getKey()])
            ->assertNotFound();

        $tipo = TipoProdotto::query()->where('name', 'Cessione')->first();
        $this->assertNotNull($tipo);
        $own->update(['tipoprodotto_id' => $tipo->getKey()]);

        Livewire::test(EditPratica::class, ['record' => $own->getKey()])
            ->assertOk()
            ->assertSee('Richiedente')
            ->assertSee('Prodotto richiesto')
            ->assertSee('Dettagli')
            // Cessione: rata; gli altri prodotti: importo richiesto e durata.
            ->assertFormFieldVisible('rata')
            ->assertFormFieldHidden('amount')
            ->assertFormFieldHidden('nrate')
            ->assertFormFieldExists('is_notowned')
            ->assertFormFieldExists('clienti_id')
            ->assertFormFieldExists('codice_pratica')
            ->assertFormFieldExists('data_inserimento_pratica')
            ->assertSee('Indirizzi')
            ->assertSee('Pratiche')
            ->assertFormSet(['cognome_cliente' => 'Rossi'])
            ->assertFormFieldExists('cliente_email')
            ->assertFormFieldExists('cliente_cellulare')
            ->assertSee('Cellulare')
            ->fillForm(['cognome_cliente' => 'Verdi'])
            ->call('save')
            ->assertHasFormErrors(['cliente_email', 'cliente_cellulare'])
            ->fillForm([
                'cliente_email' => 'verdi@example.com',
                'cliente_cellulare' => '3331234567',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Verdi', $own->fresh()->cognome_cliente);
        $this->assertTrue(
            Document::query()
                ->where('documentable_type', 'pratica')
                ->where('documentable_id', (string) $own->getKey())
                ->where('name', 'Contratto di finanziamento')
                ->exists()
        );

        $client = Client::query()->create([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'tax_code' => 'RSSMRA80A01H501U',
            'is_person' => true,
        ]);
        $own->update(['codice_fiscale' => 'RSSMRA80A01H501U']);

        Livewire::test(EditPratica::class, ['record' => $own->getKey()])
            ->assertOk()
            ->assertSee('Valutazione rischio (AML)')
            ->assertSee('KYC (adeguata verifica)')
            ->assertSee('Verifica PEP')
            ->assertSee('Cerved')
            ->fillForm([
                'cliente_email' => 'mario.rossi@example.com',
                'cliente_cellulare' => '3330001111',
                'cliente_iban' => 'it60 x054 2811 1010 0000 0123 456',
                'richiedente.is_pep' => true,
                'richiedente.status' => 'valutazione_aml',
                'richiedente.questionarioKyc.risk_level' => 'low',
                'richiedente.questionarioKyc.financing_purpose' => 'personale_familiare',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $client->refresh();
        $this->assertTrue((bool) $client->is_pep);
        $this->assertSame('valutazione_aml', $client->status);
        $this->assertSame('mario.rossi@example.com', $client->email);
        $this->assertSame('3330001111', $client->phone);
        $this->assertSame('IT60X0542811101000000123456', $client->iban);
        $this->assertSame(
            'low',
            KycQuestionnaire::query()->where('client_id', $client->getKey())->value('risk_level')?->value,
        );
    }

    public function test_perfeziona_interroga_cerved_aml_e_openapi(): void
    {
        Filament::setCurrentPanel('agenti');

        $staff = User::factory()->create(['role' => 'super_admin']);
        $agent = User::factory()->create(['role' => 'fornitore']);
        $fornitore = Fornitore::query()->create([
            'name' => 'Produttore verifiche',
            'piva' => '997'.random_int(10000000, 99999999),
            'user_id' => $agent->id,
            'is_active' => true,
        ]);
        $this->actingAs($staff);
        app(AgentImpersonationService::class)->start($staff, $fornitore);
        $this->actingAs($agent);

        config([
            'services.aml.api_key' => 'k',
            'services.aml.base_url' => 'https://aml.test',
            'services.cerved.api_key' => 'k',
            'services.cerved.url' => 'https://cerved.test/?cf=',
            'services.openapi.token' => 'tok',
            'services.openapi.company_url' => 'https://company.test',
        ]);
        Http::fake([
            'aml.test/*' => Http::response(['results' => [['confidence_score' => 0.97, 'name' => 'Mario Rossi', 'citizenship' => ['IT']]]]),
            'cerved.test/*' => Http::response([
                'id_soggetto' => '42',
                'denominazione' => 'ROSSI MARIO',
                'scores' => [[
                    'codice_score' => 'A',
                    'descrizione_score' => 'Affidabile',
                    'valore' => 80,
                    'categoria_codice' => '1',
                    'categoria_descrizione' => 'Basso',
                ]],
            ]),
            'company.test/*' => Http::response([
                'success' => true,
                'data' => [[
                    'companyName' => 'ACME S.R.L.',
                    'vatCode' => '12485671007',
                    'taxCode' => '12485671007',
                    'shareHolders' => [[
                        'surname' => 'Rossi',
                        'name' => 'Mario',
                        'taxCode' => 'RSSMRA80A01H501U',
                        'percentShare' => 60,
                    ]],
                ]],
            ]),
        ]);

        $person = Client::query()->create([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'tax_code' => 'RSSMRA80A01H501U',
            'is_person' => true,
            'email' => 'mario.rossi@example.com',
            'phone' => '3330001111',
        ]);
        $pratica = Pratica::query()->create([
            'codice_pratica' => 'ZZAPI-'.Str::upper(Str::random(4)),
            'cognome_cliente' => 'Rossi',
            'nome_cliente' => 'Mario',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'fornitori_id' => $fornitore->getKey(),
            'upload_at' => now(),
        ]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertFormSet([
                'cliente_email' => 'mario.rossi@example.com',
                'cliente_cellulare' => '3330001111',
            ])
            ->callAction(TestAction::make('cerved')->schemaComponent('codice_fiscale', 'form'))
            ->assertNotified('Cerved: ROSSI MARIO');

        Livewire::test(EditClient::class, ['record' => $person->getKey()])
            ->callAction(TestAction::make('verificaPep')->schemaComponent('valutazioneAml', 'form'))
            ->assertNotified('Verifica PEP: 1 possibili corrispondenze');

        $this->assertTrue(ApiCall::query()->where('provider', ApiProvider::Cerved)->where('subject_id', (string) $person->getKey())->exists());
        $this->assertTrue(ApiCall::query()->where('provider', ApiProvider::SanctionsIo)->where('subject_id', (string) $person->getKey())->exists());

        $company = Client::query()->create([
            'name' => 'Vecchia ragione',
            'tax_code' => '12485671007',
            'is_person' => false,
        ]);
        $societa = Pratica::query()->create([
            'codice_pratica' => 'ZZIVA-'.Str::upper(Str::random(4)),
            'cognome_cliente' => 'Vecchia ragione',
            'codice_fiscale' => '12485671007',
            'fornitori_id' => $fornitore->getKey(),
            'upload_at' => now(),
        ]);

        Livewire::test(EditPratica::class, ['record' => $societa->getKey()])
            ->fillForm([
                'cliente_email' => 'societa@example.com',
                'cliente_cellulare' => '3339998888',
                'cliente_iban' => 'IT60X0542811101000000123456',
            ])
            ->callAction(TestAction::make('openapi')->schemaComponent('cliente_iban', 'form'))
            ->assertNotified('Openapi: ACME S.R.L.');

        $this->assertTrue(ApiCall::query()->where('provider', ApiProvider::Openapi)->where('subject_id', (string) $company->getKey())->exists());
        $this->assertSame('ACME S.R.L.', $company->fresh()->name);
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Agenti\Resources\Clients\ClientResource;
use App\Filament\Agenti\Resources\Pratiche\Pages\EditPratica;
use App\Filament\Agenti\Resources\Pratiche\PraticaResource;
use App\Models\Client;
use App\Models\User;
use App\Services\Agenti\AgentImpersonationService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaClient;
use Unico\Core\Models\TipoProdotto;

class PraticaFormLayoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core', 'proforma'];

    /** @return array{0: Fornitore, 1: User} */
    private function agent(): array
    {
        Filament::setCurrentPanel('agenti');

        $staff = User::factory()->create(['role' => 'super_admin']);
        $agent = User::factory()->create(['role' => 'fornitore']);
        $fornitore = Fornitore::query()->create(['name' => 'Produttore', 'piva' => '996'.random_int(10000000, 99999999), 'user_id' => $agent->id, 'is_active' => true]);

        $this->actingAs($staff);
        app(AgentImpersonationService::class)->start($staff, $fornitore);
        $this->actingAs($agent);

        return [$fornitore, $agent];
    }

    public function test_the_product_section_follows_the_requested_order_and_switches_between_installment_and_amount(): void
    {
        [$fornitore] = $this->agent();
        $cessione = TipoProdotto::query()->where('name', 'Cessione')->firstOrFail();
        $prestito = TipoProdotto::query()->where('name', 'Prestito')->firstOrFail();
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZLAY-'.Str::upper(Str::random(4)), 'fornitori_id' => $fornitore->getKey(), 'tipoprodotto_id' => $cessione->getKey(), 'upload_at' => now()]);
        $this->collegaProvvigioneProduttore($fornitore, $pratica);

        $page = Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertSee('Impegno di terzi')
            ->assertSee('Numero pratica')
            ->assertSee('Data richiesta')
            ->assertFormFieldVisible('rata')
            ->assertFormFieldHidden('amount')
            ->assertFormFieldHidden('nrate');

        $html = $page->html();
        $order = ['Impegno di terzi', 'Tipo prodotto', 'Banca', 'Rata', 'Numero pratica', 'Data richiesta'];
        $sezione = (int) strpos($html, 'Prodotto richiesto');
        $positions = array_map(fn (string $label): int|false => strpos($html, $label, $sezione), $order);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'I campi del prodotto non seguono l\'ordine richiesto.');

        $page->fillForm(['tipoprodotto_id' => $prestito->getKey()])
            ->assertFormFieldHidden('rata')
            ->assertFormFieldVisible('amount')
            ->assertFormFieldVisible('nrate');
    }

    public function test_the_bank_link_opens_its_portal_and_saving_copies_name_and_abi_to_the_practice(): void
    {
        [$fornitore] = $this->agent();
        $banca = Cliente::query()->create(['name' => 'BANCA DI PROVA', 'abi' => '03069', 'is_active' => true, 'portal_url' => 'https://portale.banca.test/agenti']);
        $senzaPortale = Cliente::query()->create(['name' => 'BANCA SENZA PORTALE', 'is_active' => true]);
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZBNK-'.Str::upper(Str::random(4)), 'cognome_cliente' => 'Rossi', 'fornitori_id' => $fornitore->getKey(), 'upload_at' => now()]);
        $this->collegaProvvigioneProduttore($fornitore, $pratica);

        $this->assertSame('https://portale.banca.test/agenti', PraticaResource::portaleBanca($banca->getKey()));
        $this->assertNull(PraticaResource::portaleBanca($senzaPortale->getKey()));
        $this->assertNull(PraticaResource::portaleBanca(null));

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->fillForm(['clienti_id' => $banca->getKey(), 'cliente_email' => 'rossi@example.test', 'cliente_cellulare' => '3331112222'])
            ->call('save')
            ->assertHasNoFormErrors();

        $pratica->refresh();
        $this->assertSame($banca->getKey(), $pratica->clienti_id);
        $this->assertSame('BANCA DI PROVA', $pratica->denominazione_banca);
        $this->assertSame('03069', $pratica->abi);
    }

    public function test_the_tax_code_link_creates_the_client_card_when_missing_and_opens_it_afterwards(): void
    {
        [$fornitore] = $this->agent();
        $pratica = Pratica::query()->create([
            'codice_pratica' => 'ZZSCH-'.Str::upper(Str::random(4)),
            'cognome_cliente' => 'Neri',
            'nome_cliente' => 'Luca',
            'codice_fiscale' => 'NRELCU80A01H501X',
            'fornitori_id' => $fornitore->getKey(),
            'upload_at' => now(),
        ]);
        $this->collegaProvvigioneProduttore($fornitore, $pratica);
        $this->assertNull(Client::query()->where('tax_code', 'NRELCU80A01H501X')->first());

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->callAction(TestAction::make('schedaCliente')->schemaComponent('codice_fiscale', 'form'))
            ->assertRedirect();

        $client = Client::query()->where('tax_code', 'NRELCU80A01H501X')->first();
        $this->assertNotNull($client);
        $this->assertSame('Neri', $client->name);
        $this->assertTrue($client->is_person);
        $this->assertSame('richiedente', PraticaClient::query()->where('pratica_id', $pratica->getKey())->where('client_id', $client->getKey())->value('role'));

        // La seconda volta apre la stessa scheda, senza duplicarla.
        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->callAction(TestAction::make('schedaCliente')->schemaComponent('codice_fiscale', 'form'))
            ->assertRedirect(ClientResource::getUrl('edit', ['record' => $client], panel: 'agenti'));
        $this->assertSame(1, Client::query()->where('tax_code', 'NRELCU80A01H501X')->count());
    }

    public function test_the_agent_sees_only_the_clients_of_their_practices(): void
    {
        [$fornitore] = $this->agent();
        $own = Client::query()->create(['name' => 'Mio', 'tax_code' => 'MIOCLI80A01H501A', 'is_person' => true]);
        $foreign = Client::query()->create(['name' => 'Altrui', 'tax_code' => 'ALTCLI80A01H501B', 'is_person' => true]);
        Pratica::query()->create(['codice_pratica' => 'ZZCLI-'.Str::upper(Str::random(4)), 'codice_fiscale' => 'MIOCLI80A01H501A', 'fornitori_id' => $fornitore->getKey(), 'upload_at' => now()]);

        $ids = ClientResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($own->getKey(), $ids);
        $this->assertNotContains($foreign->getKey(), $ids);
    }

    public function test_the_workflow_dates_are_saved_from_the_agent_form(): void
    {
        [$fornitore] = $this->agent();
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZDATA-'.Str::upper(Str::random(4)), 'fornitori_id' => $fornitore->getKey(), 'upload_at' => now()]);
        $this->collegaProvvigioneProduttore($fornitore, $pratica);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertSee('Data presa in carico')
            ->assertSee('Data invio istruttoria')
            ->fillForm(['cliente_cellulare' => '3331234567', 'cliente_email' => 'test@example.com', 'presa_in_carico_at' => '2026-03-02', 'lavorazione_at' => '2026-03-03', 'sended_at' => '2026-03-10'])
            ->call('save')
            ->assertHasNoFormErrors();

        $pratica->refresh();
        $this->assertSame('2026-03-02', $pratica->presa_in_carico_at->toDateString());
        $this->assertSame('2026-03-03', $pratica->lavorazione_at->toDateString());
        $this->assertSame('2026-03-10', $pratica->sended_at->toDateString());
    }
}

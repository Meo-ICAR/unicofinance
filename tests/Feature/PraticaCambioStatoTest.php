<?php

namespace Tests\Feature;

use App\Filament\Agenti\Resources\Pratiche\Pages\EditPratica as EditPraticaAgente;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\Client;
use App\Models\User;
use App\Services\Agenti\AgentImpersonationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaStato;
use Unico\Core\Models\TipoProdotto;

class PraticaCambioStatoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core', 'proforma'];

    private function stato(string $name): PraticaStato
    {
        return PraticaStato::query()->firstOrCreate(['codice' => $name, 'tipo_finanziamento' => 'Cessione'], ['name' => $name, 'ordine' => 0, 'is_rejected' => false, 'is_working' => true, 'is_estingued' => false, 'colore' => 'gray']);
    }

    public function test_changing_the_state_asks_for_a_reason_and_writes_the_status_history(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($user = User::factory()->create(['role' => 'super_admin']));

        $da = $this->stato('Inserita');
        $a = $this->stato('Richiesta Istruttoria');
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZST-'.Str::upper(Str::random(4)), 'cognome_cliente' => 'Rossi', 'pratica_stato_id' => $da->getKey(), 'upload_at' => now()]);

        // Senza cambio di stato si salva subito e lo storico resta vuoto.
        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->fillForm(['cognome_cliente' => 'Rossi Verdi'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertActionNotMounted('motivoCambioStato');
        $this->assertSame(0, $pratica->praticaStatusHistory()->count());

        // Con il cambio di stato il salvataggio si ferma e chiede la motivazione.
        $page = Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->fillForm(['pratica_stato_id' => $a->getKey()])
            ->call('save')
            ->assertActionMounted('motivoCambioStato');
        $this->assertSame($da->getKey(), $pratica->fresh()->pratica_stato_id);

        // La motivazione è obbligatoria: senza, lo stato non cambia.
        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->fillForm(['pratica_stato_id' => $a->getKey()])
            ->call('save')
            ->setActionData(['motivazione' => ''])->callMountedAction()
            ->assertHasActionErrors(['motivazione' => 'required']);
        $this->assertSame($da->getKey(), $pratica->fresh()->pratica_stato_id);

        $page->setActionData(['motivazione' => 'Documenti completi, passa in istruttoria'])->callMountedAction()->assertHasNoActionErrors();

        $pratica->refresh();
        $this->assertSame($a->getKey(), $pratica->pratica_stato_id);

        $voce = $pratica->praticaStatusHistory()->firstOrFail();
        $this->assertSame($da->name, $voce->status_from);
        $this->assertSame($a->name, $voce->status_to);
        $this->assertSame('Documenti completi, passa in istruttoria', $voce->notes);
        $this->assertSame('admin', $voce->source);
        $this->assertSame($user->id, $voce->user_id);
    }

    public function test_the_edit_page_title_is_the_applicant_with_the_product_type_in_small(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $tipo = TipoProdotto::query()->where('name', 'Cessione')->firstOrFail();
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZTIT-'.Str::upper(Str::random(4)), 'cognome_cliente' => 'Acme S.r.l.', 'tipoprodotto_id' => $tipo->getKey(), 'upload_at' => now()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertSeeInOrder(['Acme S.r.l.', 'Cessione'])
            ->assertDontSeeHtml('>'.$pratica->codice_pratica.'</h1>');
    }

    public function test_the_offer_section_is_hidden_and_the_requested_sections_are_in_place(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $cessione = TipoProdotto::query()->where('name', 'Cessione')->firstOrFail();
        $prestito = TipoProdotto::query()->where('name', 'Prestito')->firstOrFail();
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZOFF-'.Str::upper(Str::random(4)), 'cognome_cliente' => 'Rossi', 'tipoprodotto_id' => $cessione->getKey(), 'upload_at' => now()]);

        $page = Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertDontSee('Offerta valida fino al')
            ->assertSee('Estremi richiedente')
            ->assertSee('Prodotto richiesto')
            ->assertFormFieldIsHidden('richiedente_persona_fisica')
            ->assertSee('Numero pratica')
            ->assertSee('Data richiesta')
            ->assertFormFieldIsVisible('rata')
            ->assertFormFieldIsVisible('nrate')
            ->assertFormFieldIsHidden('amount');

        // Rata e durata ci sono per ogni prodotto (si ricavano dalla denominazione importata); l'importo richiesto solo fuori da cessione e delega.
        $page->fillForm(['tipoprodotto_id' => $prestito->getKey()])
            ->assertFormFieldIsVisible('rata')
            ->assertFormFieldIsVisible('amount')
            ->assertFormFieldIsVisible('nrate');

        $html = $page->html();
        $sezione = (int) strpos($html, 'Prodotto richiesto');
        $positions = array_map(fn (string $label) => strpos($html, $label, $sezione), ['Tipo prodotto', 'Impegno di terzi', 'Rata', 'Durata (mesi)', 'Data richiesta', 'Numero pratica', 'Banca', 'Codice pratica banca', 'Importo richiesto']);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }

    public function test_the_mediation_is_hidden_for_third_party_products(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $proprio = TipoProdotto::query()->where('name', 'Cessione')->firstOrFail();
        $terzi = TipoProdotto::query()->where('is_third_party', true)->firstOrFail();
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZMED-'.Str::upper(Str::random(4)), 'cognome_cliente' => 'Rossi', 'tipoprodotto_id' => $proprio->getKey(), 'upload_at' => now()]);

        $page = Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertFormFieldIsVisible('mediation_fee_percent')
            ->assertFormFieldIsVisible('mediation_fee_amount');

        $page->fillForm(['tipoprodotto_id' => $terzi->getKey()])
            ->assertFormFieldIsHidden('mediation_fee_percent')
            ->assertFormFieldIsHidden('mediation_fee_amount')
            ->assertFormFieldIsHidden('mediation_fee_terms');
    }

    public function test_a_third_party_commitment_shows_only_product_bank_expiry_and_installment(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $assegno = TipoProdotto::query()->where('name', 'Assegno di mantenimento')->firstOrFail();
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZTRZ-'.Str::upper(Str::random(4)), 'cognome_cliente' => 'Rossi', 'tipoprodotto_id' => $assegno->getKey(), 'upload_at' => now()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertFormFieldIsHidden('scadenza')
            ->fillForm(['is_notowned' => true])
            ->assertFormFieldIsVisible('scadenza')
            ->assertFormFieldIsVisible('rata')
            ->assertFormFieldIsHidden('amount')
            ->assertFormFieldIsHidden('nrate')
            ->assertFormFieldIsHidden('data_inserimento_pratica')
            ->assertFormFieldIsHidden('mediation_fee_percent');
    }

    public function test_the_header_shows_the_product_description_and_the_product_section_has_no_mediation(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $cessione = TipoProdotto::query()->where('name', 'Cessione')->firstOrFail();
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZHDR-'.Str::upper(Str::random(4)), 'cognome_cliente' => 'Rossi', 'tipoprodotto_id' => $cessione->getKey(), 'denominazione_prodotto' => 'Cessione - BANCA DEL FUCINO - 270 x 120 (QT00029)', 'upload_at' => now()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertSee('Cessione - BANCA DEL FUCINO - 270 x 120 (QT00029)')
            ->assertSee('Estremi richiedente')
            ->assertDontSee('Mediazione richiesta al cliente')
            ->assertFormFieldExists('mediation_fee_percent');
    }

    public function test_the_tax_code_link_opens_the_client_card_of_the_admin_panel(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZLNK-'.Str::upper(Str::random(4)), 'cognome_cliente' => 'Neri', 'nome_cliente' => 'Luca', 'codice_fiscale' => 'NRELCU80A01H501X', 'upload_at' => now()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->callFormComponentAction('codice_fiscale', 'schedaCliente')
            ->assertRedirect();

        $this->assertSame(1, Client::query()->where('tax_code', 'NRELCU80A01H501X')->count());
    }

    public function test_the_agents_page_records_the_state_change_with_its_own_source(): void
    {
        Filament::setCurrentPanel('agenti');
        $staff = User::factory()->create(['role' => 'super_admin']);
        $agent = User::factory()->create(['role' => 'fornitore']);
        $fornitore = Fornitore::query()->create(['name' => 'Produttore', 'piva' => '995'.random_int(10000000, 99999999), 'user_id' => $agent->id, 'is_active' => true]);
        $this->actingAs($staff);
        app(AgentImpersonationService::class)->start($staff, $fornitore);
        $this->actingAs($agent);

        $da = $this->stato('Inserita');
        $a = $this->stato('Richiesta Istruttoria');
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZAG-'.Str::upper(Str::random(4)), 'cognome_cliente' => 'Bianchi', 'pratica_stato_id' => $da->getKey(), 'fornitori_id' => $fornitore->getKey(), 'upload_at' => now()]);
        $this->collegaProvvigioneProduttore($fornitore, $pratica);

        Livewire::test(EditPraticaAgente::class, ['record' => $pratica->getKey()])
            ->assertSeeInOrder(['Bianchi'])
            ->fillForm(['pratica_stato_id' => $a->getKey(), 'cliente_email' => 'b@example.test', 'cliente_cellulare' => '3331112222'])
            ->call('save')
            ->assertActionMounted('motivoCambioStato')
            ->setActionData(['motivazione' => 'Richiesta del cliente'])->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertHasNoFormErrors();

        $voce = $pratica->praticaStatusHistory()->firstOrFail();
        $this->assertSame('agenti', $voce->source);
        $this->assertSame('Richiesta del cliente', $voce->notes);
    }

    public function test_a_state_with_a_date_flag_fills_the_practice_date_with_today_only_when_empty(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $da = $this->stato('Inserita');
        $approvata = $this->stato('DELIBERATA-TEST');
        $approvata->update(['is_approved' => true]);
        $respinta = $this->stato('RESPINTA-TEST');
        $respinta->update(['is_rejected' => true]);
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZDT-'.Str::upper(Str::random(4)), 'cognome_cliente' => 'Rossi', 'pratica_stato_id' => $da->getKey(), 'upload_at' => now()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->fillForm(['pratica_stato_id' => $approvata->getKey()])
            ->call('save')
            ->setActionData(['motivazione' => 'Deliberata dalla banca'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $pratica->refresh();
        $this->assertTrue($pratica->approved_at->isToday());
        $this->assertNull($pratica->rejected_at);

        // Una data già presente non si sovrascrive.
        $pratica->update(['rejected_at' => now()->subDays(10)->toDateString(), 'pratica_stato_id' => $da->getKey()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->fillForm(['pratica_stato_id' => $respinta->getKey()])
            ->call('save')
            ->setActionData(['motivazione' => 'Respinta'])
            ->callMountedAction();

        $this->assertTrue($pratica->fresh()->rejected_at->isSameDay(now()->subDays(10)));
    }
}

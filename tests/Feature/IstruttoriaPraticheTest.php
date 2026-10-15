<?php

namespace Tests\Feature;

use App\Filament\RelationManagers\ImpegniTerziRelationManager;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Filament\Resources\Praticas\Pages\ListPraticas;
use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Document;
use Unico\Core\Models\Employee;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaClient;
use Unico\Core\Models\PraticaStatusHistory;
use Unico\Core\Models\TipoProdotto;

class IstruttoriaPraticheTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_rejected_practices_are_excluded_by_default_and_included_when_the_filter_is_removed(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $open = Pratica::query()->create(['codice_pratica' => 'ISTR-APERTA', 'cognome_cliente' => 'Rossi', 'upload_at' => now()]);
        $rejected = Pratica::query()->create(['codice_pratica' => 'ISTR-RESPINTA', 'cognome_cliente' => 'Bianchi', 'upload_at' => now(), 'rejected_at' => now()]);

        Livewire::test(ListPraticas::class)
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$rejected])
            ->removeTableFilter('escludi_respinte')
            ->assertCanSeeTableRecords([$open, $rejected]);
    }

    public function test_the_status_history_slide_over_lists_changes_newest_first_with_the_employee(): void
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($user);

        $employee = Employee::query()->create(['name' => 'Istruttore Verdi', 'user_id' => $user->id]);
        $pratica = Pratica::query()->create(['codice_pratica' => 'ISTR-STORICO', 'upload_at' => now()]);
        PraticaStatusHistory::query()->create(['pratica_id' => $pratica->getKey(), 'status_to' => 'Inserita', 'changed_at' => now()->subDay()]);
        PraticaStatusHistory::query()->create(['pratica_id' => $pratica->getKey(), 'status_from' => 'Inserita', 'status_to' => 'Istruttoria', 'changed_at' => now(), 'user_id' => $user->id]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->mountAction('cronologiaStati')
            ->assertSeeInOrder(['Istruttoria', 'Istruttore Verdi', 'Inserita']);
    }

    public function test_change_offer_clones_the_practice_with_requesters_and_documents_on_the_new_bank(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $vecchia = Cliente::query()->create(['name' => 'Banca Vecchia']);
        $nuova = Cliente::query()->create(['name' => 'Banca Nuova']);
        $tipo = TipoProdotto::query()->create(['name' => 'Cessione del quinto']);
        $pratica = Pratica::query()->create([
            'codice_pratica' => 'ISTR-OFF', 'cognome_cliente' => 'Rossi', 'amount' => 10000, 'upload_at' => now(),
            'clienti_id' => $vecchia->getKey(), 'denominazione_banca' => 'Banca Vecchia', 'tipoprodotto_id' => $tipo->getKey(),
            'sended_at' => now(),
        ]);
        PraticaClient::query()->create(['pratica_id' => $pratica->getKey(), 'client_id' => Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U'])->getKey(), 'role' => 'richiedente']);
        Document::query()->create(['documentable_type' => $pratica->getMorphClass(), 'documentable_id' => $pratica->getKey(), 'name' => 'Carta identità']);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->callAction('cambiaOfferta', ['banca_id' => $nuova->getKey(), 'assicurazione' => 'Assicura SpA'])
            ->assertHasNoActionErrors();

        $copia = Pratica::query()->where('codice_pratica', 'ISTR-OFF-OFF1')->firstOrFail();
        $this->assertSame($nuova->getKey(), $copia->clienti_id);
        $this->assertSame('Banca Nuova', $copia->denominazione_banca);
        $this->assertSame('Assicura SpA', $copia->assicurazione);
        $this->assertNull($copia->sended_at);
        $this->assertSame(1, $copia->praticaClients()->count());
        $this->assertSame(1, \App\Models\Pratica::find($copia->getKey())->documents()->count());
        $this->assertSame($vecchia->getKey(), $pratica->fresh()->clienti_id);
    }

    public function test_a_company_applicant_hides_the_first_name_and_validates_the_vat_number(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $pratica = Pratica::query()->create(['codice_pratica' => 'ISTR-PIVA', 'codice_fiscale' => '12345678903', 'cognome_cliente' => 'Acme Srl', 'upload_at' => now()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertFormSet(['richiedente_persona_fisica' => false])
            ->assertFormFieldIsHidden('nome_cliente')
            ->fillForm(['codice_fiscale' => '12345678900'])
            ->call('save')
            ->assertHasFormErrors(['codice_fiscale']);
    }

    public function test_a_person_applicant_needs_a_valid_tax_code(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $pratica = Pratica::query()->create(['codice_pratica' => 'ISTR-CF', 'codice_fiscale' => 'RSSMRA80A01H501U', 'upload_at' => now()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertFormSet(['richiedente_persona_fisica' => true])
            ->assertFormFieldIsVisible('nome_cliente')
            ->fillForm(['codice_fiscale' => 'NONVALIDO'])
            ->call('save')
            ->assertHasFormErrors(['codice_fiscale']);
    }

    public function test_openapi_fills_the_business_name_from_the_vat_number(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        config(['unico-core.openapi.token' => 'token-di-prova']);
        Http::fake(['*IT-advanced*' => Http::response(['success' => true, 'data' => [['companyName' => 'ACME S.R.L.', 'shareHolders' => [['companyName' => 'Socio Spa', 'taxCode' => '01234567897']]]]])]);
        $pratica = Pratica::query()->create(['codice_pratica' => 'ISTR-OAPI', 'codice_fiscale' => '12345678903', 'cognome_cliente' => 'Vecchio nome', 'upload_at' => now()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->callFormComponentAction('codice_fiscale', 'openapi')
            ->assertFormSet(['cognome_cliente' => 'ACME S.R.L.']);
    }

    public function test_saving_creates_the_client_only_when_missing(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $pratica = Pratica::query()->create(['codice_pratica' => 'ISTR-CLI', 'codice_fiscale' => 'RSSMRA80A01H501U', 'cognome_cliente' => 'Rossi', 'nome_cliente' => 'Mario', 'upload_at' => now()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])->call('save')->assertHasNoFormErrors();
        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])->call('save');

        $clients = Client::query()->where('tax_code', 'RSSMRA80A01H501U')->get();
        $this->assertCount(1, $clients);
        $this->assertTrue($clients->first()->is_person);
        $this->assertSame('Mario', $clients->first()->first_name);

        $societa = Pratica::query()->create(['codice_pratica' => 'ISTR-CLI2', 'codice_fiscale' => '12345678903', 'cognome_cliente' => 'Acme Srl', 'upload_at' => now()]);
        Livewire::test(EditPratica::class, ['record' => $societa->getKey()])->call('save')->assertHasNoFormErrors();

        $this->assertFalse(Client::query()->where('tax_code', '12345678903')->firstOrFail()->is_person);
    }

    public function test_third_party_commitments_list_only_the_owned_flag_rows_with_the_same_tax_code(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $cf = 'RSSMRA80A01H501U';
        $pratica = Pratica::query()->create(['codice_pratica' => 'ISTR-MAIN', 'codice_fiscale' => $cf, 'upload_at' => now()]);
        $impegno = Pratica::query()->create(['codice_pratica' => 'TERZI-1', 'codice_fiscale' => $cf, 'is_notowned' => true, 'upload_at' => now()]);
        $altraPratica = Pratica::query()->create(['codice_pratica' => 'ISTR-ALTRA', 'codice_fiscale' => $cf, 'upload_at' => now()]);
        $altroCliente = Pratica::query()->create(['codice_pratica' => 'TERZI-2', 'codice_fiscale' => 'VRDLGI85B02F205X', 'is_notowned' => true, 'upload_at' => now()]);

        Livewire::test(ImpegniTerziRelationManager::class, ['ownerRecord' => \App\Models\Pratica::find($pratica->getKey()), 'pageClass' => EditPratica::class])
            ->assertCanSeeTableRecords([$impegno])
            ->assertCanNotSeeTableRecords([$altraPratica, $altroCliente, $pratica]);
    }
}

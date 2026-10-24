<?php

namespace Tests\Feature;

use App\Filament\RelationManagers\AddressesRelationManager;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SeedsCoreData;
use Tests\TestCase;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Filament\RelationManagers\DocumentsRelationManager;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\PdfModule;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\TipoProdotto;
use Unico\Core\Pdf\PracticeModuleData;

class ClienteSchedaTabsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCoreData;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function admin(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCoreData('address_types', 'document_types', 'tasks', 'document_requirements', 'pdf_modules', 'pdf_module_fields', 'pratica_stati', 'tipoprodotto', 'tipoprodotto_sub');
    }

    public function test_info_tab_holds_privacy_lead_and_user_and_privacy_is_for_individuals_only(): void
    {
        $this->admin();
        $persona = Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);
        $societa = Client::query()->create(['name' => 'Acme Srl', 'tax_code' => '12345678903', 'is_person' => false]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->assertSee('Info')
            ->assertSee('Consensi privacy')
            ->assertFormFieldExists('general_consent_at')
            ->assertFormFieldExists('is_lead')
            ->assertSee('Utente collegato');

        Livewire::test(EditClient::class, ['record' => $societa->getKey()])
            ->assertDontSee('Consensi privacy')
            ->assertSee('Utente collegato');
    }

    public function test_the_user_is_created_and_linked_for_an_individual_with_an_email(): void
    {
        $this->admin();
        $persona = Client::query()->create(['name' => 'Rossi', 'first_name' => 'Mario', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true, 'email' => 'Mario.Rossi@example.com']);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->callAction(TestAction::make('creaUtente')->schemaComponent('user_id'))
            ->assertNotified();

        $utente = User::query()->where('email', 'mario.rossi@example.com')->firstOrFail();
        $this->assertSame($utente->getKey(), $persona->refresh()->user_id);
        $this->assertSame('client', $utente->role);
        $this->assertSame('Mario Rossi', $utente->name);
    }

    public function test_the_user_is_not_created_without_an_email(): void
    {
        $this->admin();
        $persona = Client::query()->create(['name' => 'Verdi', 'tax_code' => 'VRDLGI80A01H501U', 'is_person' => true]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->callAction(TestAction::make('creaUtente')->schemaComponent('user_id'));

        $this->assertNull($persona->refresh()->user_id);
    }

    public function test_the_kyc_module_needs_a_practice_to_be_printed(): void
    {
        $this->admin();
        $persona = Client::query()->create(['name' => 'Bianchi', 'tax_code' => 'BNCLRA80A01H501U', 'is_person' => true]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->callAction(TestAction::make('stampaModuloKyc')->schemaComponent('kyc'))
            ->assertNotified();
    }

    public function test_the_printed_kyc_can_be_sent_for_otp_signature_from_the_documents_tab(): void
    {
        $this->skipWithoutPdfTemplates();
        $this->admin();
        $persona = Client::query()->create(['name' => 'Rossi', 'first_name' => 'Mario', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true, 'email' => 'mario.rossi@example.com', 'phone' => '3330001111']);
        Pratica::query()->create(['codice_pratica' => 'KYC-OTP-1', 'codice_fiscale' => $persona->tax_code, 'tipoprodotto_id' => TipoProdotto::query()->where('name', 'Cessione')->value('id'), 'data_inserimento_pratica' => now(), 'upload_at' => now()]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->callAction(TestAction::make('stampaModuloKyc')->schemaComponent('kyc'));

        $tipo = PdfModule::query()->active()->where('name', 'like', 'QAV Persona fisica%')->firstOrFail()->documentType;
        $documento = Document::query()->where('documentable_type', 'client')->where('documentable_id', (string) $persona->getKey())->where('document_type_id', $tipo->getKey())->firstOrFail();

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $persona, 'pageClass' => EditClient::class])
            ->mountAction(TestAction::make('edit')->table($documento))
            ->assertActionVisible(TestAction::make('sendForSignature'))
            ->callAction(TestAction::make('sendForSignature'), ['cliente' => ['first_name' => 'Mario', 'last_name' => 'Rossi', 'email' => 'mario.rossi@example.com', 'phone' => '3330001111', 'tax_code' => 'RSSMRA80A01H501U']])
            ->assertNotified('Richiesta di firma inviata');

        $this->assertNotNull($documento->fresh()->latestSignatureRequest());
    }

    public function test_the_documents_tab_sends_the_documents_to_sign_as_one_bundle(): void
    {
        $this->skipWithoutPdfTemplates();
        $this->admin();
        $persona = Client::query()->create(['name' => 'Rossi', 'first_name' => 'Mario', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true, 'email' => 'mario.rossi@example.com', 'phone' => '3330001111']);
        Pratica::query()->create(['codice_pratica' => 'KYC-PLICO-1', 'codice_fiscale' => $persona->tax_code, 'tipoprodotto_id' => TipoProdotto::query()->where('name', 'Cessione')->value('id'), 'data_inserimento_pratica' => now(), 'upload_at' => now()]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->callAction(TestAction::make('stampaModuloKyc')->schemaComponent('kyc'));

        $tipo = PdfModule::query()->active()->where('name', 'like', 'QAV Persona fisica%')->firstOrFail()->documentType;
        $documento = Document::query()->where('documentable_type', 'client')->where('documentable_id', (string) $persona->getKey())->where('document_type_id', $tipo->getKey())->firstOrFail();

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $persona, 'pageClass' => EditClient::class])
            ->callAction(TestAction::make('inviaPlicoOtp')->table(), ['documenti' => [$documento->getKey()]])
            ->assertNotified();

        $richiesta = $documento->fresh()->latestSignatureRequest();
        $this->assertNotNull($richiesta);
        $this->assertNotNull($richiesta->provider_ref);
        $this->assertNotNull($richiesta->provider_document_ref);
    }

    public function test_only_identity_documents_take_number_and_issuer_and_the_latest_one_is_used(): void
    {
        $this->admin();
        $persona = Client::query()->create(['name' => 'Meo', 'first_name' => 'Piergiuseppe', 'tax_code' => 'MEOPGS64D04F839I', 'is_person' => true]);
        $documento = fn (string $nome) => $persona->documents()->whereHas('documentType', fn ($query) => $query->where('name', $nome))->firstOrFail();
        $carta = $documento('Carta d\'Identità');
        $patente = $documento('Patente di Guida');
        $gestore = fn () => Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $persona, 'pageClass' => EditClient::class]);

        // Il numero è obbligatorio per i documenti d'identità.
        $gestore()->callAction(TestAction::make('edit')->table($carta), ['docnumber' => '', 'emitted_at' => '2020-01-10'])->assertHasFormErrors(['docnumber' => 'required']);

        $gestore()->callAction(TestAction::make('edit')->table($patente), ['docnumber' => 'PAT111', 'emitted_by' => 'Motorizzazione', 'emitted_at' => '2015-01-10', 'is_monitored' => false])->assertHasNoFormErrors();
        $gestore()->callAction(TestAction::make('edit')->table($carta), ['docnumber' => 'CA12345XX', 'emitted_by' => 'Comune di Napoli', 'emitted_at' => '2024-03-01', 'is_monitored' => false])->assertHasNoFormErrors();

        $valido = app(PracticeModuleData::class)->identityDocument($persona->load('documents.documentType'));
        $this->assertSame('CA12345XX', $valido->docnumber);
        $this->assertSame('Comune di Napoli', $valido->emitted_by);

        // Per gli altri tipi i campi non esistono nemmeno.
        $altro = DocumentType::query()->where(fn ($query) => $query->whereNull('codegroup')->orWhere('codegroup', '!=', DocumentType::CODEGROUP_IDENTITA))->firstOrFail();
        $gestore()->mountAction(TestAction::make('create')->table())->fillForm(['document_type_id' => $altro->getKey()])->assertFormFieldHidden('docnumber')->assertFormFieldHidden('emitted_by');
    }

    public function test_the_addresses_tab_lists_residence_and_domicile_of_the_client(): void
    {
        $this->admin();
        $persona = Client::query()->create(['name' => 'Verdi', 'tax_code' => 'VRDLGI80A01H501U', 'is_person' => true]);

        Livewire::test(AddressesRelationManager::class, ['ownerRecord' => $persona, 'pageClass' => EditClient::class])
            ->assertCanSeeTableRecords($persona->addresses)
            ->assertCountTableRecords(2);
    }

    public function test_a_new_natural_person_gets_residence_and_domicile_addresses(): void
    {
        $persona = Client::query()->create(['name' => 'Viola', 'tax_code' => 'VLILGI80A01H501U', 'is_person' => true]);
        $societa = Client::query()->create(['name' => 'Viola Srl', 'tax_code' => '12485671007', 'is_person' => false]);

        $this->assertSame(['Residenza', 'Domicilio'], $persona->addresses()->with('addressType')->orderBy('id')->get()->map(fn ($indirizzo) => $indirizzo->addressType->name)->all());
        $this->assertSame(0, $societa->addresses()->count());
        $this->assertSame(['Carta d\'Identità', 'Patente di Guida'], $persona->documents()->with('documentType')->orderBy('id')->get()->map(fn ($documento) => $documento->documentType->name)->all());
        $this->assertSame(0, $societa->documents()->count());
    }

    public function test_the_command_fills_in_the_existing_natural_persons_without_duplicating(): void
    {
        $persona = Client::query()->create(['name' => 'Antico', 'tax_code' => 'NTCLGI80A01H501U', 'is_person' => true]);
        $persona->addresses()->delete();
        $persona->documents()->delete();

        $this->artisan('unico-core:clients-prepare')->assertSuccessful();
        $this->assertSame(2, $persona->addresses()->count());
        $this->assertSame(2, $persona->documents()->count());

        $this->artisan('unico-core:clients-prepare')->assertSuccessful();
        $this->assertSame(2, $persona->addresses()->count());
        $this->assertSame(2, $persona->documents()->count());
    }

    public function test_the_latest_practice_is_found_even_without_an_insertion_date(): void
    {
        $persona = Client::query()->create(['name' => 'Gialli', 'tax_code' => 'GLLLGI80A01H501U', 'is_person' => true]);
        Pratica::query()->create(['codice_pratica' => 'KYC-SD-1', 'codice_fiscale' => $persona->tax_code, 'upload_at' => now()]);
        $conData = Pratica::query()->create(['codice_pratica' => 'KYC-SD-2', 'codice_fiscale' => $persona->tax_code, 'data_inserimento_pratica' => now()->subDay(), 'upload_at' => now()]);

        $this->assertSame($conData->getKey(), $persona->ultimaPratica->getKey());

        $conData->delete();
        $this->assertSame('KYC-SD-1', $persona->unsetRelation('ultimaPratica')->ultimaPratica->codice_pratica);
    }

    public function test_the_client_list_search_does_not_touch_tables_missing_from_this_app(): void
    {
        $this->admin();
        $societa = Client::query()->create(['name' => 'Hassisto Srl', 'tax_code' => '12345678903', 'is_person' => false]);

        Livewire::test(ListClients::class)
            ->searchTable('hass')
            ->assertCanSeeTableRecords([$societa]);
    }

    public function test_kyc_financing_nature_is_prefilled_from_the_last_practice_and_shows_the_employer_for_salary_products(): void
    {
        $this->admin();
        $persona = Client::query()->create(['name' => 'Gialli', 'tax_code' => 'GLLMRA80A01H501U', 'is_person' => true]);
        $datore = Client::query()->create(['name' => 'Comune di Prova', 'tax_code' => '12345678903', 'is_person' => false]);
        $persona->update(['employer_id' => $datore->getKey()]);
        Pratica::query()->create(['codice_pratica' => 'KYC-1', 'codice_fiscale' => $persona->tax_code, 'tipoprodotto_id' => TipoProdotto::query()->where('name', 'Delega')->value('id'), 'data_inserimento_pratica' => now(), 'upload_at' => now()]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->assertSet('data.questionarioKyc.financing_nature', 'cessione_quinto')
            ->assertSee('Comune di Prova');
    }

    public function test_the_employer_is_not_shown_in_the_kyc_for_other_products(): void
    {
        $this->admin();
        $persona = Client::query()->create(['name' => 'Neri', 'tax_code' => 'NRELGI80A01H501U', 'is_person' => true]);
        Pratica::query()->create(['codice_pratica' => 'KYC-2', 'codice_fiscale' => $persona->tax_code, 'tipoprodotto_id' => TipoProdotto::query()->where('name', 'Mutuo')->value('id'), 'data_inserimento_pratica' => now(), 'upload_at' => now()]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->assertSet('data.questionarioKyc.financing_nature', 'mutuo')
            ->assertDontSee('si imposta in');
    }

    public function test_the_card_opens_when_the_client_already_has_a_kyc_questionnaire(): void
    {
        $this->skipWithoutPdfTemplates();
        $this->admin();
        $persona = Client::query()->create(['name' => 'Meo', 'tax_code' => 'MEOPGS64D04F839I', 'is_person' => true]);
        $persona->kycQuestionnaires()->create(['financing_purpose' => 'personale_familiare', 'status' => 'draft']);
        Pratica::query()->create(['codice_pratica' => 'KYC-3', 'codice_fiscale' => $persona->tax_code, 'tipoprodotto_id' => TipoProdotto::query()->where('name', 'Cessione')->value('id'), 'data_inserimento_pratica' => now(), 'upload_at' => now()]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->assertSuccessful()
            ->assertSee('Stampa modulo KYC')
            ->callAction(TestAction::make('stampaModuloKyc')->schemaComponent('kyc'))
            ->assertHasNoActionErrors()
            ->assertNotified('Documento d\'identità mancante');

        $tipo = PdfModule::query()->active()->where('name', 'like', 'QAV Persona fisica%')->firstOrFail()->documentType;
        $documento = Document::query()->where('documentable_type', 'client')->where('documentable_id', (string) $persona->getKey())->where('document_type_id', $tipo->getKey())->firstOrFail();
        $this->assertNotNull($documento->currentMedia());
        $this->assertSame(DocumentStatus::TO_SEND_OTP, $documento->status);
    }

    public function test_the_documents_table_prints_the_selected_modules_and_moves_them_to_the_otp_phase(): void
    {
        $this->skipWithoutPdfTemplates();
        $this->admin();
        $persona = Client::query()->create(['name' => 'Meo', 'tax_code' => 'MEOPGS64D04F839I', 'is_person' => true]);
        Pratica::query()->create(['codice_pratica' => 'KYC-4', 'codice_fiscale' => $persona->tax_code, 'tipoprodotto_id' => TipoProdotto::query()->where('name', 'Cessione')->value('id'), 'data_inserimento_pratica' => now(), 'upload_at' => now()]);
        $tipo = PdfModule::query()->active()->where('name', 'like', 'QAV Persona fisica%')->firstOrFail()->documentType;
        $documento = Document::query()->create(['documentable_type' => 'client', 'documentable_id' => (string) $persona->getKey(), 'document_type_id' => $tipo->getKey(), 'name' => $tipo->name, 'status' => DocumentStatus::PENDING]);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $persona, 'pageClass' => EditClient::class])
            ->assertTableBulkActionExists('stampaSelezionati')
            ->assertTableBulkActionExists('inviaSelezionatiOtp')
            ->callTableBulkAction('stampaSelezionati', [$documento]);

        $this->assertNotNull($documento->refresh()->currentMedia());
        $this->assertSame(DocumentStatus::TO_SEND_OTP, $documento->status);
    }

    public function test_the_scheduled_command_moves_the_unsent_modules_to_the_print_phases(): void
    {
        $this->skipWithoutPdfTemplates();
        $persona = Client::query()->create(['name' => 'Meo', 'tax_code' => 'MEOPGS64D04F839I', 'is_person' => true]);
        Pratica::query()->create(['codice_pratica' => 'KYC-5', 'codice_fiscale' => $persona->tax_code, 'tipoprodotto_id' => TipoProdotto::query()->where('name', 'Cessione')->value('id'), 'data_inserimento_pratica' => now(), 'upload_at' => now()]);
        $tipo = PdfModule::query()->active()->where('name', 'like', 'QAV Persona fisica%')->firstOrFail()->documentType;
        $documento = Document::query()->create(['documentable_type' => 'client', 'documentable_id' => (string) $persona->getKey(), 'document_type_id' => $tipo->getKey(), 'name' => $tipo->name, 'status' => DocumentStatus::PENDING]);

        $this->artisan('unico-core:documents-print-refresh')->assertSuccessful();

        $this->assertContains($documento->refresh()->status, [DocumentStatus::MISSING_DATA, DocumentStatus::PRINT]);
    }
}

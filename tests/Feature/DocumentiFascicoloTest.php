<?php

namespace Tests\Feature;

use App\Enums\ApiCallStatus;
use App\Enums\ApiProvider;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\ApiCall;
use App\Models\Pratica;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Documents\Listeners\AnalyzeUploadedDocument;
use Unico\Core\Documents\PracticeDocumentIntake;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Filament\RelationManagers\DocumentsRelationManager;
use Unico\Core\Models\Client;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentAnalysis;
use Unico\Core\Models\DocumentRequirement;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\EmailTemplate;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\PraticaClient;
use Unico\Core\Models\Task;
use Unico\Core\Models\TipoProdotto;
use Unico\Core\Pdf\PracticeModuleData;

class DocumentiFascicoloTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    /** @return array{0: Pratica, 1: array<string, Document>} */
    private function pratica(): array
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $prodotto = TipoProdotto::query()->create(['name' => 'Prodotto fascicolo', 'tipo_prodotto' => 'Prodotto fascicolo']);
        $task = Task::query()->create(['name' => 'Check list fascicolo', 'taskable' => 'pratica', 'trigger_field' => 'tipo_prodotto', 'trigger_state' => 'equals', 'trigger_value' => 'Prodotto fascicolo', 'tipoprodotto_id' => $prodotto->getKey(), 'is_active' => true]);
        $pratica = Pratica::query()->create(['codice_pratica' => 'FSC-1', 'tipoprodotto_id' => $prodotto->getKey(), 'upload_at' => now()]);
        $documenti = [];

        foreach (['verificato' => [true, false], 'caricato' => [true, false], 'dafirmare' => [true, true], 'anomalo' => [true, false], 'mancante' => [true, false], 'facoltativo' => [false, false]] as $nome => [$obbligatorio, $firma]) {
            $tipo = DocumentType::query()->create(['name' => 'Doc '.$nome, 'code' => 'FSC-'.$nome, 'is_signed' => $firma]);
            DocumentRequirement::query()->create(['task_id' => $task->getKey(), 'document_type_id' => $tipo->getKey(), 'is_required' => $obbligatorio, 'sort_order' => 1]);
            $stato = match ($nome) {
                'verificato' => DocumentStatus::APPROVED,
                'caricato', 'dafirmare' => DocumentStatus::UPLOADED,
                'anomalo' => DocumentStatus::ANOMALY,
                default => null,
            };

            if ($stato !== null) {
                $documenti[$nome] = Document::query()->create(['documentable_type' => 'pratica', 'documentable_id' => (string) $pratica->getKey(), 'document_type_id' => $tipo->getKey(), 'name' => 'Doc '.$nome, 'status' => $stato]);
            }
        }

        return [Pratica::find($pratica->getKey()), $documenti];
    }

    public function test_the_header_shows_how_far_the_documents_of_the_current_task_are(): void
    {
        [$pratica] = $this->pratica();

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class])
            ->assertSee('Check list')
            ->assertSee('3/5 documenti obbligatori presenti')
            ->assertSee('1 da firmare')
            ->assertSee('1 da correggere')
            ->assertSee('mancano: Doc anomalo, Doc mancante');
    }

    public function test_the_table_uses_icon_columns_and_the_new_filter_lists_the_documents_to_complete(): void
    {
        [$pratica, $documenti] = $this->pratica();

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class])
            ->assertCanSeeTableRecords(array_values($documenti))
            ->assertTableColumnExists('status')
            ->assertTableColumnExists('responsible')
            ->assertTableColumnExists('last_sent_at')
            ->assertTableColumnExists('reminders_count')
            ->assertTableColumnExists('scadenza')
            ->assertTableColumnDoesNotExist('fascicolo')
            ->assertTableColumnExists('controlli')
            ->assertTableColumnDoesNotExist('otp')
            ->filterTable('da_completare')
            ->assertCanSeeTableRecords([$documenti['anomalo']])
            ->assertCanNotSeeTableRecords([$documenti['verificato'], $documenti['caricato']]);
    }

    public function test_genera_plico_crea_i_documenti_mancanti_della_check_list_del_prodotto(): void
    {
        [$pratica, $documenti] = $this->pratica();

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class])
            ->callAction(TestAction::make('generaPlico')->table(), [])
            ->assertNotified();

        $this->assertSame(6, Document::query()->where('documentable_type', 'pratica')->where('documentable_id', (string) $pratica->getKey())->count());
        $this->assertSame(DocumentStatus::PENDING, Document::query()->where('name', 'Doc mancante')->firstOrFail()->status);
    }

    public function test_genera_plico_crea_anche_i_documenti_dei_client_della_pratica(): void
    {
        [$pratica] = $this->pratica();
        $task = Task::query()->where('name', 'Check list fascicolo')->firstOrFail();
        $identita = DocumentType::query()->create(['name' => 'Identità fascicolo', 'code' => 'FSC-ID', 'client_scope' => 'persona_fisica']);
        DocumentRequirement::query()->create(['task_id' => $task->getKey(), 'document_type_id' => $identita->getKey(), 'documentable' => 'client', 'client_roles' => ['richiedente', 'coobbligato'], 'is_required' => true]);
        $richiedente = Client::query()->create(['name' => 'Rossi', 'is_person' => true]);
        $coobbligato = Client::query()->create(['name' => 'Verdi', 'is_person' => true]);
        $garante = Client::query()->create(['name' => 'Neri', 'is_person' => true]);
        PraticaClient::query()->create(['pratica_id' => $pratica->getKey(), 'client_id' => $richiedente->getKey(), 'role' => 'richiedente']);
        PraticaClient::query()->create(['pratica_id' => $pratica->getKey(), 'client_id' => $coobbligato->getKey(), 'role' => 'coobbligato']);
        PraticaClient::query()->create(['pratica_id' => $pratica->getKey(), 'client_id' => $garante->getKey(), 'role' => 'garante']);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class])
            ->callAction(TestAction::make('generaPlico')->table(), [])
            ->assertNotified();

        $perClient = Document::query()->where('documentable_type', 'client')->where('document_type_id', $identita->getKey())->pluck('documentable_id')->sort()->values()->all();
        $this->assertSame(collect([$richiedente, $coobbligato])->map(fn (Client $client): string => (string) $client->getKey())->sort()->values()->all(), $perClient);
    }

    public function test_genera_plico_dalla_scheda_cliente_crea_i_documenti_del_plico_dello_stato(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $task = Task::query()->create(['name' => 'Cliente prova · raccolta dati', 'taskable' => 'client', 'trigger_field' => 'status', 'trigger_state' => 'equals', 'trigger_value' => 'raccolta_dati', 'is_active' => true]);
        $privacy = DocumentType::query()->create(['name' => 'Privacy prova', 'code' => 'CLI-PRV']);
        DocumentRequirement::query()->create(['task_id' => $task->getKey(), 'document_type_id' => $privacy->getKey(), 'is_required' => true]);
        $cliente = Client::query()->create(['name' => 'Bianchi', 'is_person' => true, 'status' => 'raccolta_dati']);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $cliente, 'pageClass' => EditClient::class])
            ->mountAction(TestAction::make('generaPlico')->table())
            ->assertSchemaStateSet(fn (array $state): bool => in_array($task->getKey(), $state['plichi'], true))
            ->callMountedAction()
            ->assertNotified();

        $this->assertTrue(Document::query()->where('documentable_type', 'client')->where('documentable_id', (string) $cliente->getKey())->where('document_type_id', $privacy->getKey())->exists());
    }

    public function test_il_documento_d_identita_del_cliente_si_completa_con_numero_e_ente_di_rilascio(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $cliente = Client::query()->create(['name' => 'Bianchi', 'first_name' => 'Anna', 'is_person' => true]);
        $carta = $cliente->documents()->whereHas('documentType', fn ($query) => $query->where('code', 'CARTA_IDENTITA'))->firstOrFail();

        // Finché non ha il numero, il documento da raccogliere non vale come identità.
        $this->assertNull(app(PracticeModuleData::class)->identityDocument($cliente->load('documents.documentType')));

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $cliente, 'pageClass' => EditClient::class])
            ->callAction(TestAction::make('edit')->table($carta), ['emitted_at' => '2024-03-01', 'is_monitored' => true, 'expires_at' => '2034-03-01', 'docnumber' => 'CA12345XX', 'emitted_by' => 'Comune di Napoli'])
            ->assertHasNoFormErrors();

        $this->assertSame('CA12345XX', $carta->refresh()->docnumber);
        $this->assertSame('Comune di Napoli', $carta->emitted_by);
        $this->assertSame($carta->getKey(), app(PracticeModuleData::class)->identityDocument($cliente->load('documents.documentType'))?->getKey());
    }

    public function test_the_document_can_be_assigned_to_who_must_move_the_iter_forward(): void
    {
        [$pratica, $documenti] = $this->pratica();
        $produttore = Fornitore::query()->create(['name' => 'Produttore Iter', 'piva' => '9'.random_int(1000000000, 9999999999), 'is_active' => true]);
        $pratica->update(['fornitori_id' => $produttore->getKey()]);
        $documento = $documenti['caricato'];

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $pratica->fresh(), 'pageClass' => EditPratica::class])
            ->callAction(TestAction::make('edit')->table($documento), ['responsible_type' => 'fornitore', 'responsible_id' => $produttore->getKey()])
            ->assertHasNoFormErrors();

        $this->assertSame('fornitore', $documento->refresh()->responsible_type);
        $this->assertSame('Produttore Iter', $documento->responsible->name);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $pratica->fresh(), 'pageClass' => EditPratica::class])
            ->assertTableColumnStateSet('responsible', 'Produttore: Produttore Iter', $documento);
    }

    public function test_the_table_filters_by_who_has_the_document_and_sends_the_reminder_to_that_subject(): void
    {
        [$pratica, $documenti] = $this->pratica();
        $produttore = Fornitore::query()->create(['name' => 'Produttore Sollecito', 'email' => 'produttore.sollecito@example.test', 'piva' => '9'.random_int(1000000000, 9999999999), 'is_active' => true]);
        EmailTemplate::query()->firstOrCreate(['code' => 'DOC_EXPIRING', 'severity' => null, 'company_id' => null, 'app_identifier' => null], ['name' => 'Sollecito base', 'subject' => 'Documenti da inviare', 'body' => '<p>Gentile {agente_nome}, mancano documenti.</p>', 'is_active' => true]);
        $uno = $documenti['caricato'];
        $due = $documenti['dafirmare'];
        $altro = $documenti['verificato'];
        $uno->update(['responsible_type' => 'fornitore', 'responsible_id' => (string) $produttore->getKey()]);
        $due->update(['responsible_type' => 'fornitore', 'responsible_id' => (string) $produttore->getKey()]);
        $due->forceFill(['reminders_count' => 2])->saveQuietly();
        Mail::fake();

        $tabella = fn () => Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class]);

        $tabella()
            ->filterTable('responsible', ['responsible_type' => 'fornitore', 'responsible_id' => (string) $produttore->getKey()])
            ->assertCanSeeTableRecords([$uno, $due])
            ->assertCanNotSeeTableRecords([$altro]);

        $tabella()
            ->callTableBulkAction('inviaSollecito', [$uno, $altro])
            ->assertNotified('Sollecito non inviato');
        Mail::assertNothingSent();

        $tabella()
            ->callTableBulkAction('inviaSollecito', [$uno, $due])
            ->assertNotified('Sollecito inviato');
        $this->assertSame(1, $uno->refresh()->reminders_count);
        $this->assertSame(3, $due->refresh()->reminders_count);
    }

    public function test_the_ai_error_shows_a_red_icon_linked_to_the_call_log_and_the_instructions_open_the_edit(): void
    {
        [$pratica, $documenti] = $this->pratica();
        $documento = $documenti['caricato'];
        $documento->documentType->update(['instructions' => 'Carica entrambe le facciate, leggibili.']);
        $chiamata = ApiCall::query()->create(['provider' => ApiProvider::Anthropic, 'operation' => 'lettura', 'status' => ApiCallStatus::Failed, 'error' => 'Chiamata AI fallita (HTTP 529)', 'cost' => 0]);
        DocumentAnalysis::query()->create(['document_id' => $documento->getKey(), 'kind' => 'extraction', 'outcome' => 'needs_review', 'operator_type' => 'ai', 'operator_label' => 'ai.test', 'error' => 'RuntimeException', 'result' => ['fields' => null, 'api_log' => (string) $chiamata->getKey()]]);
        $altro = $documenti['verificato'];
        DocumentAnalysis::query()->create(['document_id' => $altro->getKey(), 'kind' => 'verification', 'outcome' => 'passed', 'operator_type' => 'procedural', 'operator_label' => 'verifier.technical', 'result' => []]);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class])
            ->assertTableColumnStateSet('controlli', 'errore_ai', $documento)
            ->assertTableColumnStateSet('controlli', 'passed', $altro)
            ->assertSeeHtml('chiamate-api/'.$chiamata->getKey())
            ->mountAction(TestAction::make('edit')->table($documento))
            ->assertSchemaComponentVisible('istruzioni_operatore');
    }

    public function test_the_corrected_file_of_a_rejected_document_becomes_a_new_version_and_the_old_keeps_its_history(): void
    {
        [$pratica, $documenti] = $this->pratica();
        $vecchio = $documenti['anomalo'];
        $vecchio->forceFill(['status' => DocumentStatus::REJECTED, 'rejection_note' => 'Foto sfocata'])->saveQuietly();
        AnalyzeUploadedDocument::suspendedWhile(fn () => $vecchio->addMediaFromString('%PDF-1.4 vecchio')->usingFileName('vecchio.pdf')->toMediaCollection(Document::COLLECTION));

        $vecchio->refresh()->addMediaFromString('%PDF-1.4 corretto')->usingFileName('corretto.pdf')->toMediaCollection(Document::COLLECTION);

        $vecchio->refresh();
        $nuovo = Document::query()->findOrFail($vecchio->renewed_by);
        $this->assertSame(DocumentStatus::REJECTED, $vecchio->status, 'Il documento respinto resta com\'è, con la sua nota.');
        $this->assertSame('Foto sfocata', $vecchio->rejection_note);
        $this->assertSame(['vecchio.pdf'], $vecchio->getMedia(Document::COLLECTION)->pluck('file_name')->all());
        $this->assertSame(['corretto.pdf'], $nuovo->getMedia(Document::COLLECTION)->pluck('file_name')->all());
        $this->assertSame($vecchio->document_type_id, $nuovo->document_type_id);
        $this->assertNotContains($vecchio->getKey(), Document::query()->current()->pluck('id')->all(), 'La versione vecchia non è più quella corrente.');
        $this->assertContains($nuovo->getKey(), Document::query()->current()->pluck('id')->all());
    }

    public function test_a_zip_upload_creates_one_document_per_file(): void
    {
        [$pratica] = $this->pratica();
        $identita = DocumentType::query()->create(['name' => 'Carta zip', 'code' => 'ZIP-ID', 'regex' => '/carta/i']);
        $reddito = DocumentType::query()->create(['name' => 'Busta zip', 'code' => 'ZIP-BP', 'regex' => '/busta/i']);
        $percorso = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new \ZipArchive;
        $zip->open($percorso, \ZipArchive::OVERWRITE);
        $zip->addFromString('carta-identita.pdf', "%PDF-1.4\ncarta");
        $zip->addFromString('cartella/busta-paga.pdf', "%PDF-1.4\nbusta");
        $zip->close();

        $risultati = app(PracticeDocumentIntake::class)->intakeAll($pratica, new UploadedFile($percorso, 'documenti.zip', 'application/zip', null, true), collect([$identita, $reddito]));

        $this->assertCount(2, $risultati);
        $this->assertEqualsCanonicalizing([$identita->getKey(), $reddito->getKey()], array_map(fn ($risultato) => $risultato->document->document_type_id, $risultati));
        $this->assertSame(2, Document::query()->where('documentable_type', 'pratica')->where('documentable_id', (string) $pratica->getKey())->whereIn('document_type_id', [$identita->getKey(), $reddito->getKey()])->count());
    }
}

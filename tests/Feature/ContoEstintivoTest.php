<?php

namespace Tests\Feature;

use App\Filament\Resources\Praticas\Pages\CreatePratica;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Mail\ContoEstintivoInBanca;
use App\Models\User;
use App\Services\Istruttoria\InvioContoEstintivo;
use App\Services\Istruttoria\RichiestaContoEstintivo;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Mockery;
use Tests\Concerns\SeedsCoreData;
use Tests\TestCase;
use Unico\Core\Documents\PracticeSignaturePackage;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Branch;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentRequest;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Pratica;

class ContoEstintivoTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCoreData;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function impegno(?Cliente $istituto): Pratica
    {
        return Pratica::query()->create(['codice_pratica' => 'IMP-1', 'cognome_cliente' => 'Rossi', 'upload_at' => now(), 'is_notowned' => true, 'clienti_id' => $istituto?->getKey()]);
    }

    private function richieste(Pratica $pratica): int
    {
        return DocumentRequest::query()->where('requestable_type', 'pratica')->where('requestable_id', (string) $pratica->getKey())->where('name', 'Conto estintivo')->count();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCoreData('companies');
    }

    public function test_the_payoff_statement_is_requested_only_when_the_institute_has_an_estinzioni_branch(): void
    {
        DocumentType::query()->firstOrCreate(['code' => RichiestaContoEstintivo::TIPO_DOCUMENTO], ['name' => 'Conto estintivo']);
        DocumentType::query()->firstOrCreate(['name' => RichiestaContoEstintivo::TIPO_RICHIESTA]);
        $conFiliale = Cliente::query()->create(['name' => 'Finanziaria Uno', 'is_active' => false]);
        Branch::query()->create(['company_id' => 1, 'name' => 'Estinzioni', 'branchable_type' => 'cliente', 'branchable_id' => (string) $conFiliale->getKey()]);
        $senzaFiliale = Cliente::query()->create(['name' => 'Finanziaria Due', 'is_active' => false]);
        $servizio = app(RichiestaContoEstintivo::class);

        $pratica = $this->impegno($conFiliale);
        $this->assertNull($servizio->sincronizza($pratica));
        $this->assertNull($servizio->sincronizza($pratica));
        $this->assertSame(1, $this->richieste($pratica));
        $this->assertSame(1, Document::query()->where('documentable_id', (string) $pratica->getKey())->where('name', RichiestaContoEstintivo::TIPO_RICHIESTA)->where('status', DocumentStatus::PRINT)->count());

        $altra = $this->impegno($senzaFiliale);
        $this->assertStringContainsString('filiale Estinzioni', (string) $servizio->sincronizza($altra));
        $this->assertSame(0, $this->richieste($altra));

        $this->assertNull($servizio->sincronizza($this->impegno(null)));
    }

    public function test_the_payoff_statement_goes_to_the_bank_only_after_the_instructor_approved_it(): void
    {
        Mail::fake();
        $tipo = DocumentType::query()->firstOrCreate(['code' => RichiestaContoEstintivo::TIPO_DOCUMENTO], ['name' => 'Conto estintivo']);
        $pratica = $this->impegno(Cliente::query()->create(['name' => 'Finanziaria Uno', 'is_active' => false]));
        $documento = Document::query()->create(['documentable_type' => 'pratica', 'documentable_id' => (string) $pratica->getKey(), 'document_type_id' => $tipo->getKey(), 'name' => 'Conto estintivo', 'status' => DocumentStatus::UPLOADED]);
        $documento->addMediaFromString('conteggio')->usingFileName('conto.pdf')->toMediaCollection(Document::COLLECTION);
        $servizio = app(InvioContoEstintivo::class);

        $this->assertNull($servizio->documentoApprovato($pratica));
        $this->expectExceptionMessage('non è ancora stato caricato e approvato');
        try {
            $servizio->invia($pratica, 'banca@example.test');
        } finally {
            Mail::assertNothingSent();
        }
    }

    public function test_the_approved_payoff_statement_is_mailed_to_the_bank_with_the_file(): void
    {
        Mail::fake();
        $tipo = DocumentType::query()->firstOrCreate(['code' => RichiestaContoEstintivo::TIPO_DOCUMENTO], ['name' => 'Conto estintivo']);
        $pratica = $this->impegno(Cliente::query()->create(['name' => 'Finanziaria Uno', 'is_active' => false]));
        $documento = Document::query()->create(['documentable_type' => 'pratica', 'documentable_id' => (string) $pratica->getKey(), 'document_type_id' => $tipo->getKey(), 'name' => 'Conto estintivo', 'status' => DocumentStatus::APPROVED]);
        $documento->addMediaFromString('conteggio')->usingFileName('conto.pdf')->toMediaCollection(Document::COLLECTION);

        app(InvioContoEstintivo::class)->invia($pratica, 'banca@example.test');

        Mail::assertSent(ContoEstintivoInBanca::class, fn (ContoEstintivoInBanca $mail): bool => $mail->hasTo('banca@example.test') && count($mail->attachments()) === 1);
        $this->assertNotNull($documento->fresh()->last_sent_at);
    }

    public function test_the_payoff_request_form_goes_to_otp_signature_from_the_practice_page(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $tipo = DocumentType::query()->firstOrCreate(['name' => RichiestaContoEstintivo::TIPO_RICHIESTA]);
        $pratica = $this->impegno(Cliente::query()->create(['name' => 'Finanziaria Uno', 'is_active' => false]));
        Document::query()->create(['documentable_type' => 'pratica', 'documentable_id' => (string) $pratica->getKey(), 'document_type_id' => $tipo->getKey(), 'name' => $tipo->name, 'status' => DocumentStatus::PRINT]);

        $firma = Mockery::mock(PracticeSignaturePackage::class);
        $firma->shouldReceive('status')->andReturn([$tipo->getKey() => ['state' => 'pronto', 'name' => $tipo->name, 'missing' => [], 'document' => null]]);
        $firma->shouldReceive('send')->once()->andReturn([['name' => $tipo->name, 'ok' => true, 'message' => 'inviato per la firma con OTP']]);
        $this->app->instance(PracticeSignaturePackage::class, $firma);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertActionVisible('firmaRichiestaConteggio')
            ->callAction('firmaRichiestaConteggio')
            ->assertNotified();
    }

    public function test_a_third_party_commitment_can_be_saved_with_an_inactive_institute_or_none(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $inattivo = Cliente::query()->create(['name' => 'Finanziaria Spenta', 'is_active' => false]);

        Livewire::test(CreatePratica::class)
            ->fillForm(['codice_pratica' => 'IMP-2', 'cognome_cliente' => 'Bianchi', 'is_notowned' => true, 'clienti_id' => $inattivo->getKey()])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($inattivo->getKey(), Pratica::query()->where('codice_pratica', 'IMP-2')->value('clienti_id'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\KycQuestionnaire;
use App\Models\Pratica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCoreData;
use Tests\Support\FakeDocumentReader;
use Tests\TestCase;
use Unico\Core\Documents\Contracts\DocumentReader;
use Unico\Core\Documents\DocumentPipeline;
use Unico\Core\Documents\Listeners\AnalyzeUploadedDocument;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\PraticaClient;

class QuestionarioKycLettoTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCoreData;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function qavCaricato(Pratica $pratica): Document
    {
        $tipo = DocumentType::query()->firstOrCreate(['code' => 'QAV-TEST'], ['name' => 'QAV Persona fisica', 'ai_kind' => 'questionario_kyc']);
        $documento = Document::query()->create(['documentable_type' => 'pratica', 'documentable_id' => (string) $pratica->getKey(), 'document_type_id' => $tipo->getKey(), 'name' => 'QAV']);
        AnalyzeUploadedDocument::suspendedWhile(fn () => $documento->addMediaFromString("%PDF-1.4\nquestionario firmato")->usingFileName('qav.pdf')->toMediaCollection(Document::COLLECTION));

        return $documento->fresh();
    }

    public function test_the_signed_questionnaire_fills_the_client_the_domicile_and_the_kyc_without_overwriting(): void
    {
        $this->seedCoreData('address_types');
        $this->app->instance(DocumentReader::class, new FakeDocumentReader);
        $pratica = Pratica::query()->create(['codice_pratica' => 'QT-KYC-1', 'upload_at' => now()]);

        app(DocumentPipeline::class)->run($this->qavCaricato($pratica), ['classify' => false]);

        $client = Client::query()->where('tax_code', 'RSSMRA80A01H501U')->firstOrFail();
        $this->assertSame('Italia', $client->citizenship);
        $domicilio = $client->addresses()->where('address_type_id', 2)->firstOrFail();
        $this->assertSame(['Via Verdi 7', 'Roma', 'RM', '00100'], [$domicilio->street, $domicilio->city, $domicilio->province, $domicilio->zip_code]);

        $kyc = KycQuestionnaire::query()->where('client_id', $client->getKey())->firstOrFail();
        $this->assertSame('complete', $kyc->status->value);
        $this->assertSame(['nessuna', 'autonomo', 'prestito_personale', 'fino_100k'], [$kyc->pep_status->value, $kyc->economic_activity->value, $kyc->financing_nature->value, $kyc->income_band->value]);

        // Una risposta corretta a mano non si sovrascrive con una nuova lettura.
        $kyc->update(['income_band' => '100k_250k']);
        app(DocumentPipeline::class)->run($this->qavCaricato($pratica), ['classify' => false]);
        $this->assertSame('100k_250k', $kyc->fresh()->income_band->value);
        $this->assertSame(1, KycQuestionnaire::query()->where('client_id', $client->getKey())->count());
    }

    public function test_the_questionnaire_of_a_guarantor_fills_only_the_guarantor_and_leaves_the_applicant_alone(): void
    {
        $this->seedCoreData('address_types');
        $this->app->instance(DocumentReader::class, new FakeDocumentReader);
        $pratica = Pratica::query()->create(['codice_pratica' => 'QT-KYC-2', 'codice_fiscale' => 'BNCLRA85A41H501Z', 'cognome_cliente' => 'Bianchi', 'nome_cliente' => 'Laura', 'upload_at' => now()]);
        $richiedente = Client::query()->create(['name' => 'Bianchi', 'first_name' => 'Laura', 'tax_code' => 'BNCLRA85A41H501Z', 'is_person' => true]);
        PraticaClient::query()->create(['pratica_id' => $pratica->getKey(), 'client_id' => $richiedente->getKey(), 'role' => 'richiedente', 'name' => 'Laura Bianchi']);
        $garante = Client::query()->create(['name' => 'Rossi', 'first_name' => 'Mario', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);
        PraticaClient::query()->create(['pratica_id' => $pratica->getKey(), 'client_id' => $garante->getKey(), 'role' => 'garante', 'name' => 'Mario Rossi']);

        $tipo = DocumentType::query()->firstOrCreate(['code' => 'QAV-TEST'], ['name' => 'QAV Persona fisica', 'ai_kind' => 'questionario_kyc']);
        $documento = Document::query()->create(['documentable_type' => 'client', 'documentable_id' => (string) $garante->getKey(), 'document_type_id' => $tipo->getKey(), 'name' => 'QAV garante']);
        AnalyzeUploadedDocument::suspendedWhile(fn () => $documento->addMediaFromString("%PDF-1.4\nquestionario garante")->usingFileName('qav.pdf')->toMediaCollection(Document::COLLECTION));

        app(DocumentPipeline::class)->run($documento->fresh(), ['classify' => false]);

        $kyc = KycQuestionnaire::query()->where('client_id', $garante->getKey())->firstOrFail();
        $this->assertSame((string) $pratica->getKey(), $kyc->pratica_id);
        $this->assertSame('nessuna', $kyc->pep_status->value);
        $this->assertSame('Italia', $garante->fresh()->citizenship);
        $this->assertSame(1, $garante->addresses()->where('address_type_id', 2)->count());
        $this->assertSame(0, KycQuestionnaire::query()->where('client_id', $richiedente->getKey())->count());
        $this->assertNull($richiedente->fresh()->citizenship);
        $this->assertSame(0, $richiedente->addresses()->whereNotNull('street')->count());
        $this->assertSame(2, PraticaClient::query()->where('pratica_id', $pratica->getKey())->count());
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ApiCallStatus;
use App\Enums\ApiProvider;
use App\Filament\Resources\ApiCalls\Pages\ListApiCalls;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\ApiCall;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Filament\RelationManagers\DocumentsRelationManager;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaStatusHistory;

class ExcelExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    /** Il bootstrap dei test occupa già circa 110 MB e lo zip dell'xlsx legge a blocchi da 16 MB: 128 MB non bastano qui, in una richiesta web sì. */
    protected function setUp(): void
    {
        parent::setUp();

        ini_set('memory_limit', '256M');
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
    }

    public function test_the_table_export_downloads_the_rows_with_the_filters_line_and_the_total(): void
    {
        foreach ([1.5, 2.25] as $cost) {
            ApiCall::query()->create(['provider' => ApiProvider::Cerved, 'operation' => 'test-export-excel', 'status' => ApiCallStatus::Success, 'cost' => $cost]);
        }

        $rows = $this->scaricaExcel(Livewire::test(ListApiCalls::class)->searchTable('test-export-excel'), TestAction::make('excelExport')->table());

        $this->assertStringContainsString('FILTRI APPLICATI: Nessuno', $rows[0][0]);
        $costo = array_search('Costo', $rows[2], true);
        $this->assertNotFalse($costo);
        $this->assertCount(2, array_filter(array_slice($rows, 3), fn (array $row): bool => $row[0] !== 'GRAN TOTALE COMPLESSIVO'));
        $this->assertSame('GRAN TOTALE COMPLESSIVO', end($rows)[0]);
        $this->assertEqualsWithDelta(3.75, end($rows)[$costo], 0.001);
    }

    public function test_the_documents_of_a_record_can_be_exported(): void
    {
        $societa = Client::query()->create(['name' => 'Acme Srl', 'vat_number' => '12345678903', 'tax_code' => '12345678903', 'is_person' => false]);
        $tipo = DocumentType::query()->create(['name' => 'Visura export di prova', 'code' => 'VIS-XLS', 'is_client' => true]);
        Document::query()->create(['documentable_type' => 'client', 'documentable_id' => (string) $societa->getKey(), 'document_type_id' => $tipo->getKey(), 'name' => 'Visura Acme', 'status' => DocumentStatus::PENDING]);

        $rows = $this->scaricaExcel(Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $societa, 'pageClass' => EditClient::class]), TestAction::make('export')->table());

        $this->assertNotEmpty(array_filter($rows, fn (array $row): bool => in_array('Visura Acme', $row, true) || in_array('Visura export di prova', $row, true)));
    }

    public function test_the_status_history_of_a_pratica_can_be_downloaded(): void
    {
        $pratica = Pratica::query()->create(['codice_pratica' => 'XLS-STORICO', 'upload_at' => now()]);
        PraticaStatusHistory::query()->create(['pratica_id' => $pratica->getKey(), 'status_to' => 'Inserita', 'changed_at' => now()->subDay()]);
        PraticaStatusHistory::query()->create(['pratica_id' => $pratica->getKey(), 'status_from' => 'Inserita', 'status_to' => 'Istruttoria', 'changed_at' => now(), 'notes' => 'Documenti completi']);

        $rows = $this->scaricaExcel(Livewire::test(EditPratica::class, ['record' => $pratica->getKey()]), ['cronologiaStati', 'scaricaCronologia']);

        $this->assertSame('Pratica XLS-STORICO', $rows[0][0]);
        $this->assertSame(['Inserita', 'Istruttoria'], [$rows[3][1], $rows[3][2]]);
        $this->assertSame('Documenti completi', $rows[3][7]);
        $this->assertSame('Inserita', $rows[4][2]);
    }

    /**
     * @param  TestAction|list<string>  $azione
     * @return list<list<mixed>>
     */
    private function scaricaExcel(Testable $component, TestAction|array $azione): array
    {
        $download = $component->callAction($azione)->effects['download'];
        $this->assertStringEndsWith('.xlsx', $download['name']);

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, base64_decode($download['content']));
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false);
        unlink($path);

        return $rows;
    }
}

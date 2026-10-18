<?php

namespace Tests\Feature;

use App\Filament\Resources\Solleciti\Pages\ListSolleciti;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Client;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Employee;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaStato;

class SollecitiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function pratica(string $codice, array $attributi = []): Pratica
    {
        return Pratica::query()->create(array_merge(['codice_pratica' => $codice, 'cognome_cliente' => 'Rossi', 'upload_at' => now(), 'presa_in_carico_at' => '2026-10-05'], $attributi));
    }

    private function documento(Pratica $pratica, string $nome, ?Client $responsabile = null, DocumentStatus $stato = DocumentStatus::PENDING): Document
    {
        $tipo = DocumentType::query()->firstOrCreate(['name' => $nome], ['code' => strtoupper($nome)]);
        $documento = Document::query()->create(['documentable_type' => 'pratica', 'documentable_id' => (string) $pratica->getKey(), 'name' => $nome, 'document_type_id' => $tipo->getKey(), 'status' => $stato]);

        if ($responsabile !== null) {
            $documento->forceFill(['responsible_type' => 'client', 'responsible_id' => (string) $responsabile->getKey()])->saveQuietly();
        }

        return $documento;
    }

    public function test_only_pending_documents_of_open_practices_taken_over_from_october_2026_are_listed(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $sospesa = PraticaStato::query()->create(['codice' => 'SOSPESA', 'name' => 'Sospesa', 'is_suspended' => true]);

        $valido = $this->documento($this->pratica('P-OK'), 'Busta paga');
        $vecchia = $this->documento($this->pratica('P-OLD', ['presa_in_carico_at' => '2026-09-30']), 'Busta paga');
        $inviata = $this->documento($this->pratica('P-INV', ['sended_at' => '2026-10-10']), 'Busta paga');
        $respinta = $this->documento($this->pratica('P-RIF', ['rejected_at' => '2026-10-10']), 'Busta paga');
        $inSospeso = $this->documento($this->pratica('P-SOS', ['pratica_stato_id' => $sospesa->getKey()]), 'Busta paga');
        $caricato = $this->documento($this->pratica('P-CAR'), 'Busta paga', stato: DocumentStatus::UPLOADED);

        Livewire::test(ListSolleciti::class)
            ->assertCanSeeTableRecords([$valido])
            ->assertCanNotSeeTableRecords([$vecchia, $inviata, $respinta, $inSospeso, $caricato]);
    }

    public function test_the_bulk_action_sends_one_email_per_practice_and_recipient_with_the_instructor_in_copy(): void
    {
        Mail::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $istruttore = Employee::query()->create(['name' => 'Istr Uno', 'email' => 'istr@races.it', 'employee_types' => 'istruttore', 'is_active' => true]);
        $cliente = Client::query()->create(['name' => 'Rossi', 'first_name' => 'Mario', 'email' => 'mario@example.test', 'is_person' => true]);
        $pratica = $this->pratica('P-MAIL', ['employee_id' => $istruttore->getKey()]);
        $uno = $this->documento($pratica, 'Busta paga', $cliente);
        $due = $this->documento($pratica, 'Documento identità', $cliente);

        Livewire::test(ListSolleciti::class)
            ->filterTable('istruttore', $istruttore->getKey())
            ->filterTable('tipologia', 'client')
            ->callTableBulkAction('sollecita', [$uno, $due]);

        Mail::assertSentCount(1);
        $this->assertSame(1, $uno->fresh()->reminders_count);
        $this->assertNotNull($due->fresh()->last_sent_at);
    }
}

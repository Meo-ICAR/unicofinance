<?php

namespace Tests\Feature;

use App\Filament\Widgets\DaPrendereInCarico;
use App\Filament\Widgets\DocumentiDaSeguire;
use App\Filament\Widgets\InFirma;
use App\Filament\Widgets\PraticheInCorso;
use App\Filament\Widgets\RisposteBancaInSospeso;
use App\Filament\Widgets\ScadenzeDocumenti;
use App\Models\Pratica;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\Employee;
use Unico\Core\Models\PraticaStato;

class DashboardIstruttoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function istruttore(): Employee
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($user);

        return Employee::query()->create(['name' => 'Istruttore Test', 'user_id' => $user->id]);
    }

    private function stato(array $flags = []): PraticaStato
    {
        return PraticaStato::query()->create(['codice' => 'ST-'.uniqid(), 'tipo_finanziamento' => 'Test', 'name' => 'Stato '.uniqid(), 'ordine' => 0, 'is_working' => true, 'colore' => 'gray'] + $flags);
    }

    public function test_unassigned_practices_are_listed_and_taken_over_by_the_current_instructor(): void
    {
        $istruttore = $this->istruttore();
        $nuova = Pratica::query()->create(['codice_pratica' => 'DSH-1', 'cognome_cliente' => 'Rossi', 'data_inserimento_pratica' => now()->subDays(3)->toDateString(), 'upload_at' => now()]);
        $vecchia = Pratica::query()->create(['codice_pratica' => 'DSH-2', 'cognome_cliente' => 'Storica', 'data_inserimento_pratica' => now()->subDays(400)->toDateString(), 'upload_at' => now()]);
        $respinta = Pratica::query()->create(['codice_pratica' => 'DSH-3', 'cognome_cliente' => 'Respinta', 'data_inserimento_pratica' => now()->toDateString(), 'rejected_at' => now()->toDateString(), 'upload_at' => now()]);
        $sospesa = Pratica::query()->create(['codice_pratica' => 'DSH-4', 'cognome_cliente' => 'Sospesa', 'data_inserimento_pratica' => now()->toDateString(), 'pratica_stato_id' => $this->stato(['is_suspended' => true])->getKey(), 'upload_at' => now()]);

        Livewire::test(DaPrendereInCarico::class)
            ->assertCanSeeTableRecords([$nuova])
            ->assertCanNotSeeTableRecords([$vecchia, $respinta, $sospesa])
            ->callTableAction('prendiInCarico', $nuova)
            ->assertNotified('Pratica presa in carico');

        $this->assertSame($istruttore->getKey(), (int) $nuova->fresh()->employee_id);
    }

    public function test_a_user_without_an_employee_card_cannot_take_over_a_practice(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $pratica = Pratica::query()->create(['codice_pratica' => 'DSH-5', 'data_inserimento_pratica' => now()->toDateString(), 'upload_at' => now()]);

        Livewire::test(DaPrendereInCarico::class)
            ->callTableAction('prendiInCarico', $pratica)
            ->assertNotified('Nessuna scheda dipendente');

        $this->assertNull($pratica->fresh()->employee_id);
    }

    public function test_in_progress_practices_default_to_the_current_instructor(): void
    {
        $istruttore = $this->istruttore();
        $altro = Employee::query()->create(['name' => 'Altro Istruttore']);
        $mia = Pratica::query()->create(['codice_pratica' => 'DSH-6', 'cognome_cliente' => 'Mia', 'employee_id' => $istruttore->getKey(), 'upload_at' => now()]);
        $sua = Pratica::query()->create(['codice_pratica' => 'DSH-7', 'cognome_cliente' => 'Sua', 'employee_id' => $altro->getKey(), 'upload_at' => now()]);
        $erogata = Pratica::query()->create(['codice_pratica' => 'DSH-8', 'cognome_cliente' => 'Erogata', 'employee_id' => $istruttore->getKey(), 'erogated_at' => now()->toDateString(), 'upload_at' => now()]);

        Livewire::test(PraticheInCorso::class)
            ->assertCanSeeTableRecords([$mia])
            ->assertCanNotSeeTableRecords([$sua, $erogata])
            ->removeTableFilter('mie')
            ->assertCanSeeTableRecords([$mia, $sua])
            ->assertCanNotSeeTableRecords([$erogata]);
    }

    public function test_documents_to_follow_are_those_missing_or_to_fix_on_my_open_practices(): void
    {
        $istruttore = $this->istruttore();
        $altro = Employee::query()->create(['name' => 'Altro Istruttore']);
        $mia = Pratica::query()->create(['codice_pratica' => 'DSH-9', 'cognome_cliente' => 'Mia', 'employee_id' => $istruttore->getKey(), 'upload_at' => now()]);
        $sua = Pratica::query()->create(['codice_pratica' => 'DSH-10', 'cognome_cliente' => 'Sua', 'employee_id' => $altro->getKey(), 'upload_at' => now()]);

        $crea = fn (Pratica $pratica, DocumentStatus $stato): Document => Document::query()->create(['documentable_type' => 'pratica', 'documentable_id' => (string) $pratica->getKey(), 'name' => 'Doc '.$stato->value, 'status' => $stato]);
        $mancante = $crea($mia, DocumentStatus::PENDING);
        $anomalo = $crea($mia, DocumentStatus::ANOMALY);
        $approvato = $crea($mia, DocumentStatus::APPROVED);
        $altrui = $crea($sua, DocumentStatus::PENDING);

        Livewire::test(DocumentiDaSeguire::class)
            ->assertCanSeeTableRecords([$mancante, $anomalo])
            ->assertCanNotSeeTableRecords([$approvato, $altrui])
            ->removeTableFilter('mie')
            ->assertCanSeeTableRecords([$mancante, $anomalo, $altrui]);
    }

    public function test_the_dashboard_shows_the_three_instructor_widgets(): void
    {
        $this->istruttore();

        $this->get('/admin')
            ->assertOk()
            ->assertSeeLivewire(DaPrendereInCarico::class)
            ->assertSeeLivewire(PraticheInCorso::class)
            ->assertSeeLivewire(DocumentiDaSeguire::class)
            ->assertSeeLivewire(ScadenzeDocumenti::class)
            ->assertSeeLivewire(RisposteBancaInSospeso::class)
            ->assertSeeLivewire(InFirma::class);
    }

    public function test_expiring_documents_include_the_expired_and_those_due_soon_on_my_open_practices(): void
    {
        $istruttore = $this->istruttore();
        $mia = Pratica::query()->create(['codice_pratica' => 'DSH-11', 'cognome_cliente' => 'Mia', 'employee_id' => $istruttore->getKey(), 'upload_at' => now()]);
        $crea = fn (string $nome, string $scadenza): Document => Document::query()->create(['documentable_type' => 'pratica', 'documentable_id' => (string) $mia->getKey(), 'name' => $nome, 'status' => DocumentStatus::UPLOADED, 'expires_at' => $scadenza]);
        $scaduto = $crea('Scaduto', now()->subDays(5)->toDateString());
        $inScadenza = $crea('In scadenza', now()->addDays(10)->toDateString());
        $lontano = $crea('Lontano', now()->addDays(200)->toDateString());

        Livewire::test(ScadenzeDocumenti::class)
            ->assertCanSeeTableRecords([$scaduto, $inScadenza])
            ->assertCanNotSeeTableRecords([$lontano]);
    }

    public function test_sent_practices_without_an_answer_are_listed_after_the_chosen_days(): void
    {
        $istruttore = $this->istruttore();
        $ferma = Pratica::query()->create(['codice_pratica' => 'DSH-12', 'cognome_cliente' => 'Ferma', 'employee_id' => $istruttore->getKey(), 'sended_at' => now()->subDays(20)->toDateString(), 'upload_at' => now()]);
        $recente = Pratica::query()->create(['codice_pratica' => 'DSH-13', 'cognome_cliente' => 'Recente', 'employee_id' => $istruttore->getKey(), 'sended_at' => now()->subDays(2)->toDateString(), 'upload_at' => now()]);
        $approvata = Pratica::query()->create(['codice_pratica' => 'DSH-14', 'cognome_cliente' => 'Approvata', 'employee_id' => $istruttore->getKey(), 'sended_at' => now()->subDays(30)->toDateString(), 'approved_at' => now()->toDateString(), 'upload_at' => now()]);

        Livewire::test(RisposteBancaInSospeso::class)
            ->assertCanSeeTableRecords([$ferma])
            ->assertCanNotSeeTableRecords([$recente, $approvata])
            ->filterTable('da', '0')
            ->assertCanSeeTableRecords([$ferma, $recente]);
    }

    public function test_the_signature_widget_renders_open_requests(): void
    {
        $this->istruttore();

        Livewire::test(InFirma::class)->assertOk();
    }
}

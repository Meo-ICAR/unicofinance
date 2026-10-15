<?php

namespace Tests\Feature;

use App\Filament\Agenti\Resources\Scadenziario\Pages\ListScadenziario;
use App\Models\Pratica;
use App\Models\Provvigione;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Fornitore;

class ScadenziarioAgenteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core', 'proforma'];

    private Fornitore $agente;

    private Fornitore $collaboratore;

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('proforma')->table('provvigioni_statos')->insertOrIgnore(['stato' => 'Inserito']);
        Filament::setCurrentPanel('agenti');
        $user = User::factory()->create(['role' => 'fornitore']);
        $this->actingAs($user);
        $this->agente = Fornitore::query()->create(['name' => 'Agente Scad', 'piva' => '97654321098', 'user_id' => $user->id, 'is_active' => true]);
        $this->collaboratore = Fornitore::query()->create(['name' => 'Collaboratore Scad', 'piva' => '97654321099', 'is_active' => true]);
    }

    /** Pratica con una provvigione dell'agente (diretta, oppure di un collaboratore con la sua quota di coordinamento). */
    private function pratica(string $cognome, Fornitore $produttore, bool $conProvvigione = true): Pratica
    {
        $pratica = Pratica::query()->create(['codice_pratica' => 'SCD-'.Str::upper(Str::random(5)), 'cognome_cliente' => $cognome, 'fornitori_id' => $produttore->getKey(), 'upload_at' => now()]);

        if ($conProvvigione) {
            (new Provvigione)->forceFill([
                'id' => (string) Str::uuid(), 'id_pratica' => $pratica->getKey(), 'importo' => 10, 'stato' => 'Inserito', 'entrata_uscita' => 'Uscita',
                'piva' => $this->agente->piva, 'denominazione_riferimento' => $this->agente->name, 'annullato' => false,
            ])->save();
        }

        return $pratica;
    }

    private function documento(Pratica $pratica, string $nome, bool $dafirmare, array $attributi = []): Document
    {
        $tipo = DocumentType::query()->create(['name' => 'Tipo '.$nome, 'code' => 'T-'.Str::upper(Str::random(6)), 'is_signed' => $dafirmare]);

        return Document::query()->create(['documentable_type' => 'pratica', 'documentable_id' => (string) $pratica->getKey(), 'document_type_id' => $tipo->getKey(), 'name' => $nome, 'status' => DocumentStatus::UPLOADED] + $attributi);
    }

    public function test_by_default_only_the_documents_to_sign_of_the_practices_with_my_commissions_are_listed(): void
    {
        $diretta = $this->pratica('Diretta', $this->agente);
        $delCollaboratore = $this->pratica('Collab', $this->collaboratore);
        $senzaProvvigione = $this->pratica('Estranea', $this->collaboratore, false);

        $daFirmare = $this->documento($diretta, 'Fascicolo', true);
        $firmato = $this->documento($diretta, 'Informativa', true, ['signed_at' => now()]);
        $normale = $this->documento($diretta, 'Identità', false);
        $daFirmareCollab = $this->documento($delCollaboratore, 'Fascicolo collab', true);
        $estraneo = $this->documento($senzaProvvigione, 'Fascicolo estraneo', true);

        Livewire::test(ListScadenziario::class)
            ->assertCanSeeTableRecords([$daFirmare, $daFirmareCollab])
            ->assertCanNotSeeTableRecords([$firmato, $normale, $estraneo])
            ->filterTable('alla_firma', null)
            ->assertCanSeeTableRecords([$daFirmare, $firmato, $normale, $daFirmareCollab])
            ->assertCanNotSeeTableRecords([$estraneo]);
    }

    public function test_the_origin_filter_separates_direct_practices_from_those_of_collaborators(): void
    {
        $diretta = $this->pratica('Diretta', $this->agente);
        $delCollaboratore = $this->pratica('Collab', $this->collaboratore);
        $mio = $this->documento($diretta, 'Fascicolo mio', true);
        $suo = $this->documento($delCollaboratore, 'Fascicolo suo', true);

        Livewire::test(ListScadenziario::class)
            ->filterTable('origine', 'dirette')
            ->assertCanSeeTableRecords([$mio])
            ->assertCanNotSeeTableRecords([$suo])
            ->filterTable('origine', 'collaboratori')
            ->assertCanSeeTableRecords([$suo])
            ->assertCanNotSeeTableRecords([$mio]);
    }

    public function test_the_expiring_filter_shows_expired_and_soon_due_documents(): void
    {
        $pratica = $this->pratica('Scad', $this->agente);
        $scaduto = $this->documento($pratica, 'Scaduto', false, ['expires_at' => now()->subDays(3)->toDateString()]);
        $lontano = $this->documento($pratica, 'Lontano', false, ['expires_at' => now()->addDays(200)->toDateString()]);

        Livewire::test(ListScadenziario::class)
            ->filterTable('alla_firma', null)
            ->filterTable('in_scadenza', true)
            ->assertCanSeeTableRecords([$scaduto])
            ->assertCanNotSeeTableRecords([$lontano]);
    }
}

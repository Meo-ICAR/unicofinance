<?php

namespace Tests\Feature;

use App\Filament\Agenti\Resources\Pratiche\Pages\EditPratica;
use App\Filament\Agenti\Widgets\ProvvigioniAgente;
use App\Filament\Agenti\Widgets\SuntoProvvigioniAgente;
use App\Filament\RelationManagers\ProvvigioniRelationManager;
use App\Models\Pratica;
use App\Models\Provvigione;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\PraticaStato;

class ProvvigioniAgenteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core', 'proforma'];

    private Fornitore $agente;

    protected function setUp(): void
    {
        parent::setUp();

        // Gli stati ammessi del compenso sono una tabella di Proforma (chiave esterna).
        foreach (['Inserito', 'Pagato', 'Sospeso'] as $stato) {
            DB::connection('proforma')->table('provvigioni_statos')->insertOrIgnore(['stato' => $stato]);
        }

        Filament::setCurrentPanel('agenti');
        $user = User::factory()->create(['role' => 'fornitore']);
        $this->actingAs($user);
        $this->agente = Fornitore::query()->create(['name' => 'Agente Provv', 'piva' => '98765432101', 'user_id' => $user->id, 'is_active' => true]);
    }

    private function pratica(string $cognome, array $attributi = []): Pratica
    {
        return Pratica::query()->create(['codice_pratica' => 'PRV-'.Str::upper(Str::random(5)), 'cognome_cliente' => $cognome, 'fornitori_id' => $this->agente->getKey(), 'upload_at' => now()] + $attributi);
    }

    private function provvigione(Pratica $pratica, ?string $stato, float $importo, array $attributi = []): Provvigione
    {
        $provvigione = (new Provvigione)->forceFill($attributi + [
            'id' => (string) Str::uuid(), 'id_pratica' => $pratica->codice_pratica, 'cognome' => $pratica->cognome_cliente, 'descrizione' => 'Provvigione test',
            'importo' => $importo, 'stato' => $stato, 'entrata_uscita' => 'Uscita', 'piva' => $this->agente->piva,
            'denominazione_riferimento' => $this->agente->name, 'annullato' => false,
        ]);
        $provvigione->save();

        return $provvigione;
    }

    public function test_the_practice_commissions_show_the_agent_only_his_own_with_the_percentage(): void
    {
        $pratica = $this->pratica('Verdi');
        $sua = $this->provvigione($pratica, 'Inserito', 100, ['percentuale' => 40]);
        $istituto = $this->provvigione($pratica, 'Inserito', 999, ['entrata_uscita' => 'Entrata', 'tipo' => 'Istituto']);
        $altrui = $this->provvigione($pratica, 'Inserito', 70, ['piva' => '11111111111', 'denominazione_riferimento' => 'Altro agente']);

        Livewire::test(ProvvigioniRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class])
            ->assertCanSeeTableRecords([$sua])
            ->assertCanNotSeeTableRecords([$istituto, $altrui])
            ->assertTableColumnExists('percentuale')
            ->assertTableColumnStateSet('percentuale', '40.0000', $sua)
            ->assertTableColumnHidden('entrata_uscita');
    }

    public function test_by_default_only_the_matured_commissions_of_the_agent_are_listed_with_the_practice_state(): void
    {
        $stato = PraticaStato::query()->create(['codice' => 'PRV-ST', 'tipo_finanziamento' => 'Test', 'name' => 'Erogata test', 'ordine' => 0, 'is_working' => true, 'colore' => 'gray']);
        $pratica = $this->pratica('Rossi', ['pratica_stato_id' => $stato->getKey()]);
        $maturata = $this->provvigione($pratica, 'Inserito', 100);
        $inMaturazione = $this->provvigione($pratica, null, 50);
        $pagata = $this->provvigione($pratica, 'Pagato', 30);
        $entrata = $this->provvigione($pratica, 'Inserito', 999, ['entrata_uscita' => 'Entrata']);
        $altrui = $this->provvigione($pratica, 'Inserito', 70, ['piva' => '11111111111', 'denominazione_riferimento' => 'Altro agente']);

        Livewire::test(ProvvigioniAgente::class)
            ->assertCanSeeTableRecords([$maturata])
            ->assertCanNotSeeTableRecords([$inMaturazione, $pagata, $entrata, $altrui])
            ->assertSee('Rossi')
            ->assertSee('Erogata test')
            ->removeTableFilter('maturazione')
            ->assertCanSeeTableRecords([$maturata, $inMaturazione, $pagata])
            ->assertCanNotSeeTableRecords([$entrata, $altrui]);
    }

    public function test_commissions_of_declined_or_suspended_practices_are_excluded(): void
    {
        $sospesa = PraticaStato::query()->create(['codice' => 'PRV-SO', 'tipo_finanziamento' => 'Test', 'name' => 'Sospesa test', 'ordine' => 0, 'is_working' => true, 'is_suspended' => true, 'colore' => 'gray']);
        $declinata = PraticaStato::query()->create(['codice' => 'PRV-DE', 'tipo_finanziamento' => 'Test', 'name' => 'Declinata test', 'ordine' => 0, 'is_working' => true, 'is_rejected' => true, 'colore' => 'gray']);
        $buona = $this->provvigione($this->pratica('Buona'), 'Inserito', 10);
        $dellaSospesa = $this->provvigione($this->pratica('Sospesa', ['pratica_stato_id' => $sospesa->getKey()]), 'Inserito', 20);
        $dellaDeclinata = $this->provvigione($this->pratica('Declinata', ['pratica_stato_id' => $declinata->getKey()]), 'Inserito', 30);
        $dellaRespinta = $this->provvigione($this->pratica('Respinta', ['rejected_at' => now()->toDateString()]), 'Inserito', 40);

        Livewire::test(ProvvigioniAgente::class)
            ->removeTableFilter('maturazione')
            ->assertCanSeeTableRecords([$buona])
            ->assertCanNotSeeTableRecords([$dellaSospesa, $dellaDeclinata, $dellaRespinta]);
    }

    public function test_the_summary_totals_each_stage_without_the_excluded_practices(): void
    {
        $pratica = $this->pratica('Rossi');
        $this->provvigione($pratica, 'Inserito', 100);
        $this->provvigione($pratica, 'Inserito', 25.5);
        $this->provvigione($pratica, null, 50);
        $this->provvigione($pratica, 'Pagato', 30);
        $this->provvigione($this->pratica('Respinta', ['rejected_at' => now()->toDateString()]), 'Inserito', 1000);

        Livewire::test(SuntoProvvigioniAgente::class)
            ->assertSee('125,50')
            ->assertSee('50,00')
            ->assertSee('30,00')
            ->assertDontSee('1.125');
    }
}

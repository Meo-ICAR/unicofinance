<?php

namespace Tests\Feature;

use App\Filament\Resources\ListinoProvvigionales\Pages\EditListinoProvvigionale;
use App\Filament\Resources\ListinoProvvigionales\Pages\ListListinoProvvigionales;
use App\Filament\Resources\ListinoProvvigionales\RelationManagers\VociRelationManager;
use App\Models\ListinoProvvigionale;
use App\Models\ListinoProvvigionaleVoce;
use App\Models\User;
use App\Services\Provvigioni\PercentualeListino;
use Database\Seeders\ListiniReteSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\TipoProdotto;

class ListiniReteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function voci(string $nome, bool $coordinamento): array
    {
        return ListinoProvvigionale::query()->where('name', $nome)->where('coordinamento', $coordinamento)->firstOrFail()->voci->pluck('percentuale', 'voce')->map(fn ($v) => (float) $v)->sortKeys()->all();
    }

    public function test_the_network_listini_are_created_from_the_distribution_structure(): void
    {
        $this->seed(ListiniReteSeeder::class);

        $this->assertSame(['special' => 40.0, 'standard' => 60.0], $this->voci('Cessione Consulente', false));
        $this->assertSame(['special' => 60.0, 'standard' => 90.0], $this->voci('Delega Premium', false));
        $this->assertSame(['banca' => 65.0, 'mediazione' => 63.0, 'polizza' => 55.0], $this->voci('Mutuo Special', false));
        $this->assertSame(['banca' => 65.0], $this->voci('Prestito Premium', false));
        $this->assertSame(['consulenza' => 50.0], $this->voci('Aziendale Special', false));
        $this->assertSame(['brokeraggio' => 75.0, 'premio' => 65.0], $this->voci('Polizza Premium', false));

        // Over provvigionali indirette: il Supervisore ha il 3% di consulenza sull'aziendale, il Premium con coordinamento il 10%.
        $this->assertSame(['consulenza' => 10.0], $this->voci('Aziendale Coordinamento Premium', true));
        $this->assertSame(['consulenza' => 3.0], $this->voci('Aziendale Supervisore', true));
        $this->assertSame(['banca' => 10.0, 'mediazione' => 5.0, 'polizza' => 5.0], $this->voci('Mutuo Supervisore', true));
    }

    public function test_seeding_twice_does_not_duplicate_listini_or_voci(): void
    {
        $this->seed(ListiniReteSeeder::class);
        $listini = ListinoProvvigionale::query()->count();
        $voci = ListinoProvvigionaleVoce::query()->count();

        $this->seed(ListiniReteSeeder::class);

        $this->assertSame($listini, ListinoProvvigionale::query()->count());
        $this->assertSame($voci, ListinoProvvigionaleVoce::query()->count());
    }

    public function test_the_main_percentage_follows_the_first_voice_and_the_resource_lists_the_voices(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->seed(ListiniReteSeeder::class);
        $listino = ListinoProvvigionale::query()->where('name', 'Cessione Special')->where('coordinamento', false)->firstOrFail();

        $listino->voci()->where('voce', 'standard')->first()->update(['percentuale' => 82]);

        $this->assertEquals(82, $listino->fresh()->value);

        Livewire::test(ListListinoProvvigionales::class)
            ->assertCanSeeTableRecords([$listino])
            ->assertSee('Standard 82%');

        Livewire::test(VociRelationManager::class, ['ownerRecord' => $listino, 'pageClass' => EditListinoProvvigionale::class])
            ->assertCanSeeTableRecords($listino->voci);
    }

    public function test_delega_listini_are_assigned_like_the_cessione_ones_of_the_same_level(): void
    {
        $fornitore = Fornitore::query()->create(['name' => 'Produttore Delega', 'piva' => '9'.random_int(1000000000, 9999999999), 'is_active' => true]);
        $this->seed(ListiniReteSeeder::class);
        $cessione = ListinoProvvigionale::query()->where('name', 'Cessione Special')->where('coordinamento', false)->firstOrFail();
        $cessione->assegnazioni()->create(['fornitori_id' => $fornitore->getKey(), 'predefinito' => true]);

        $this->seed(ListiniReteSeeder::class);

        $delega = ListinoProvvigionale::query()->where('name', 'Delega Special')->where('coordinamento', false)->firstOrFail();
        $assegnazione = $delega->assegnazioni()->where('fornitori_id', $fornitore->getKey())->first();

        $this->assertNotNull($assegnazione);
        $this->assertFalse((bool) $assegnazione->predefinito);
    }

    public function test_the_percentages_of_a_producer_come_from_the_listino_assigned_for_the_product(): void
    {
        $this->seed(ListiniReteSeeder::class);
        $fornitore = Fornitore::query()->create(['name' => 'Produttore Mutui', 'piva' => '9'.random_int(1000000000, 9999999999), 'is_active' => true]);
        $special = ListinoProvvigionale::query()->where('name', 'Mutuo Special')->where('coordinamento', false)->firstOrFail();
        $premium = ListinoProvvigionale::query()->where('name', 'Mutuo Premium')->where('coordinamento', false)->firstOrFail();
        $special->assegnazioni()->create(['fornitori_id' => $fornitore->getKey(), 'predefinito' => false]);
        $premium->assegnazioni()->create(['fornitori_id' => $fornitore->getKey(), 'predefinito' => true]);

        $servizio = app(PercentualeListino::class);

        // Con più listini dello stesso prodotto vale il predefinito; senza listino di coordinamento non c'è nulla.
        $this->assertSame($premium->getKey(), $servizio->listino($fornitore->getKey(), $premium->tipoprodotto_id)->getKey());
        $this->assertNull($servizio->listino($fornitore->getKey(), $premium->tipoprodotto_id, true));
        $this->assertNull($servizio->listino($fornitore->getKey(), null));
    }

    public function test_the_coverage_report_lists_producers_without_a_listino_for_the_products_they_handle(): void
    {
        $this->seed(ListiniReteSeeder::class);
        $fornitore = Fornitore::query()->create(['name' => 'Produttore Scoperto', 'piva' => '9'.random_int(1000000000, 9999999999), 'is_active' => true]);
        $mutuo = TipoProdotto::query()->where('name', 'Mutuo')->firstOrFail();
        Pratica::query()->create(['codice_pratica' => 'RSC-1', 'fornitori_id' => $fornitore->getKey(), 'tipoprodotto_id' => $mutuo->getKey(), 'upload_at' => now()]);

        $senza = app(PercentualeListino::class)->senzaRiscontro()->where('fornitore_id', $fornitore->getKey());

        $this->assertCount(1, $senza);
        $this->assertSame($mutuo->getKey(), $senza->first()['tipoprodotto_id']);

        ListinoProvvigionale::query()->where('name', 'Mutuo Special')->where('coordinamento', false)->firstOrFail()->assegnazioni()->create(['fornitori_id' => $fornitore->getKey(), 'predefinito' => true]);

        $this->assertCount(0, app(PercentualeListino::class)->senzaRiscontro()->where('fornitore_id', $fornitore->getKey()));
    }

    public function test_the_report_ignores_inactive_producers_old_practices_and_follows_the_product_cluster(): void
    {
        $this->seed(ListiniReteSeeder::class);
        $servizio = app(PercentualeListino::class);
        $nuovoFornitore = fn (string $nome, bool $attivo) => Fornitore::query()->create(['name' => $nome, 'piva' => '9'.random_int(1000000000, 9999999999), 'is_active' => $attivo]);
        $prodotto = fn (string $nome) => TipoProdotto::query()->firstOrCreate(['name' => $nome], ['tipo_prodotto' => $nome]);
        $pratica = fn ($fornitore, $tipo, string $data) => Pratica::query()->create(['codice_pratica' => 'RSC-'.uniqid(), 'fornitori_id' => $fornitore->getKey(), 'tipoprodotto_id' => $tipo->getKey(), 'data_inserimento_pratica' => $data, 'upload_at' => now()]);

        $attivo = $nuovoFornitore('Attivo', true);
        $inattivo = $nuovoFornitore('Cessato', false);
        $mutuo = $prodotto('Mutuo');
        $pratica($attivo, $mutuo, '2026-08-10');
        $pratica($inattivo, $mutuo, '2026-08-10');

        $dal = Carbon::parse('2026-07-01');
        $ids = $servizio->senzaRiscontro($dal)->pluck('fornitore_id');

        $this->assertTrue($ids->contains($attivo->getKey()));
        $this->assertFalse($ids->contains($inattivo->getKey()));

        // Le pratiche precedenti alla data non contano.
        $vecchio = $nuovoFornitore('Vecchio', true);
        $pratica($vecchio, $mutuo, '2026-03-10');
        $this->assertFalse($servizio->senzaRiscontro($dal)->pluck('fornitore_id')->contains($vecchio->getKey()));

        // Il chirografario segue i listini dell'Aziendale.
        $chirografario = $prodotto('CHIROGRAFARIO');
        $corporate = $nuovoFornitore('Corporate', true);
        $pratica($corporate, $chirografario, '2026-09-01');
        ListinoProvvigionale::query()->where('name', 'Aziendale Special')->where('coordinamento', false)->firstOrFail()->assegnazioni()->create(['fornitori_id' => $corporate->getKey(), 'predefinito' => true]);

        $this->assertFalse($servizio->senzaRiscontro($dal)->pluck('fornitore_id')->contains($corporate->getKey()));
        $this->assertSame('Aziendale Special', $servizio->listino($corporate->getKey(), $chirografario->getKey())->name);
    }
}

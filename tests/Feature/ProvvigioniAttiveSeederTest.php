<?php

namespace Tests\Feature;

use Database\Seeders\ProvvigioniAttiveSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\ProvvigioniRule;
use Unico\Core\Models\TipoProdotto;

class ProvvigioniAttiveSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function regole(string $prodotto, ?string $banca)
    {
        $tipo = TipoProdotto::query()->where('name', $prodotto)->firstOrFail();
        $query = ProvvigioniRule::query()->where('tipoprodotto_id', (string) $tipo->getKey())->whereNull('fornitori_id')->whereNull('kind_id');

        return $banca === null
            ? $query->whereNull('clienti_id')->orderBy('ltv_min')->get()
            : $query->where('clienti_id', (string) Cliente::query()->where('name', $banca)->firstOrFail()->getKey())->orderBy('ltv_min')->get();
    }

    public function test_assignment_by_product_and_bank(): void
    {
        $this->seed(ProvvigioniAttiveSeeder::class);

        // Cessione e delega: libere, sul lordo (rata x numero rate).
        foreach (['Cessione', 'Delega'] as $prodotto) {
            $regola = $this->regole($prodotto, 'IBL BANCA SPA')->sole();
            $this->assertSame('lordo', $regola->tipo_provvigioni);
            $this->assertNull($regola->value);
        }

        // Prestiti: quota fissa degli interessi.
        $agos = $this->regole('Prestito', 'AGOS SPA')->sole();
        $this->assertSame('interessi', $agos->tipo_provvigioni);
        $this->assertEquals(40, $agos->value);

        // Mutuo: una regola per fascia di LTV, senza buchi fra le fasce.
        $fasce = $this->regole('Mutuo', 'BNL');
        $this->assertSame([[0, 50], [50, 70], [70, 80], [80, 100]], $fasce->map(fn (ProvvigioniRule $regola): array => [(int) $regola->ltv_min, (int) $regola->ltv_max])->all());
        $this->assertSame('erogato', $fasce->first()->tipo_provvigioni);
        $this->assertTrue($fasce->first()->value > $fasce->last()->value, 'più LTV, meno provvigione');

        // Gli altri prodotti hanno valori propri e un riferimento senza banca.
        $this->assertSame('premio', $this->regole('Polizza', 'ASSICURAPOINT')->sole()->tipo_provvigioni);
        $this->assertSame('fixed', $this->regole('Utenza', 'SWITCHO')->sole()->tipo_provvigioni);
        $this->assertCount(1, $this->regole('Microcredito', null));
        $this->assertCount(4, $this->regole('Mutuo', null));
    }

    public function test_the_seeder_can_be_repeated(): void
    {
        $this->seed(ProvvigioniAttiveSeeder::class);
        $totale = ProvvigioniRule::query()->count();

        $this->seed(ProvvigioniAttiveSeeder::class);

        $this->assertSame($totale, ProvvigioniRule::query()->count());
    }
}

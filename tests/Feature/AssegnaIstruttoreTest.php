<?php

namespace Tests\Feature;

use App\Models\Pratica;
use App\Services\Istruttoria\AssegnaIstruttore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Employee;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\PraticaStato;
use Unico\Core\Models\TipoProdotto;

class AssegnaIstruttoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string|null>
     */
    protected array $connectionsToTransact = [null, 'core'];

    /**
     * @param  array<string, mixed>  $specializzazione
     */
    private function istruttore(string $nome, array $specializzazione = []): Employee
    {
        return Employee::query()->create(['name' => $nome, 'is_active' => true, 'employee_roles' => [AssegnaIstruttore::RUOLO]] + $specializzazione);
    }

    private function pratica(?TipoProdotto $prodotto, ?Cliente $banca = null): Pratica
    {
        return Pratica::query()->create(['codice_pratica' => 'ASS-'.uniqid(), 'tipoprodotto_id' => $prodotto?->getKey(), 'clienti_id' => $banca?->getKey(), 'upload_at' => now()]);
    }

    public function test_the_product_specialist_gets_the_practice(): void
    {
        $prestiti = TipoProdotto::query()->create(['name' => 'Prestito ZZ']);
        $cessioni = TipoProdotto::query()->create(['name' => 'Cessione ZZ']);
        $bastione = $this->istruttore('Bastione', ['tipoprodotto_ids' => [$prestiti->getKey()]]);
        $gravina = $this->istruttore('Gravina', ['tipoprodotto_ids' => [$cessioni->getKey()]]);

        $prestito = $this->pratica($prestiti);
        $cessione = $this->pratica($cessioni);

        app(AssegnaIstruttore::class)->assegna($prestito);
        app(AssegnaIstruttore::class)->assegna($cessione);

        $this->assertSame($bastione->getKey(), $prestito->fresh()->employee_id);
        $this->assertSame($gravina->getKey(), $cessione->fresh()->employee_id);
    }

    public function test_the_bank_specialist_wins_over_the_product_specialist_and_a_later_bank_choice_reassigns(): void
    {
        $prestiti = TipoProdotto::query()->create(['name' => 'Prestito ZZ']);
        $capitalfin = Cliente::query()->create(['name' => 'CAPITALFIN ZZ']);
        $bastione = $this->istruttore('Bastione', ['tipoprodotto_ids' => [$prestiti->getKey()]]);
        $zaccaria = $this->istruttore('Zaccaria', ['clienti_ids' => [$capitalfin->getKey()]]);

        $pratica = $this->pratica($prestiti);
        app(AssegnaIstruttore::class)->assegna($pratica);
        $this->assertSame($bastione->getKey(), $pratica->fresh()->employee_id, 'Senza banca va allo specialista del prodotto.');

        $pratica->update(['clienti_id' => $capitalfin->getKey()]);
        app(AssegnaIstruttore::class)->assegna($pratica->fresh());
        $this->assertSame($zaccaria->getKey(), $pratica->fresh()->employee_id, 'Scelta la banca passa allo specialista della banca.');
    }

    public function test_a_specialist_of_something_else_is_skipped_and_a_generalist_takes_the_rest(): void
    {
        $prestiti = TipoProdotto::query()->create(['name' => 'Prestito ZZ']);
        $mutui = TipoProdotto::query()->create(['name' => 'Mutuo ZZ']);
        $this->istruttore('Bastione', ['tipoprodotto_ids' => [$prestiti->getKey()]]);
        $generico = $this->istruttore('Generico');

        $pratica = $this->pratica($mutui);
        app(AssegnaIstruttore::class)->assegna($pratica);

        $this->assertSame($generico->getKey(), $pratica->fresh()->employee_id);
    }

    public function test_among_equal_candidates_the_one_with_fewer_open_practices_gets_it(): void
    {
        $this->istruttore('Primo');
        $secondo = $this->istruttore('Secondo');
        $primo = Employee::query()->where('name', 'Primo')->sole();
        $stato = PraticaStato::query()->create(['codice' => 'ST-ASS', 'tipo_finanziamento' => 'Test', 'name' => 'Stato ASS', 'ordine' => 0, 'colore' => 'gray']);
        Pratica::query()->create(['codice_pratica' => 'ASS-CARICO', 'employee_id' => $primo->getKey(), 'pratica_stato_id' => $stato->getKey(), 'upload_at' => now()]);

        $pratica = $this->pratica(null);
        app(AssegnaIstruttore::class)->assegna($pratica);

        $this->assertSame($secondo->getKey(), $pratica->fresh()->employee_id);
    }

    public function test_an_inactive_employee_or_one_without_the_role_is_never_chosen(): void
    {
        Employee::query()->create(['name' => 'Inattivo', 'is_active' => false, 'employee_roles' => [AssegnaIstruttore::RUOLO]]);
        Employee::query()->create(['name' => 'Altro ruolo', 'is_active' => true, 'employee_roles' => ['commerciale']]);

        $pratica = $this->pratica(null);

        $this->assertNull(app(AssegnaIstruttore::class)->assegna($pratica));
        $this->assertNull($pratica->fresh()->employee_id);
    }

    public function test_an_agent_who_is_also_an_employee_gets_his_own_practice(): void
    {
        $prestiti = TipoProdotto::query()->create(['name' => 'Prestito ZZ']);
        $this->istruttore('Bastione', ['tipoprodotto_ids' => [$prestiti->getKey()]]);
        $fummo = Employee::query()->create(['name' => 'FUMMO BRIGIDA', 'is_active' => true]);
        $agente = Fornitore::query()->create(['name' => 'Brigida  Fummo', 'piva' => '99900000021', 'is_active' => true]);
        $altro = Fornitore::query()->create(['name' => 'ZZ Agente Esterno', 'piva' => '99900000022', 'is_active' => true]);

        $sua = $this->pratica($prestiti);
        $sua->update(['fornitori_id' => $agente->getKey()]);
        $altrui = $this->pratica($prestiti);
        $altrui->update(['fornitori_id' => $altro->getKey()]);

        app(AssegnaIstruttore::class)->assegna($sua->fresh());
        app(AssegnaIstruttore::class)->assegna($altrui->fresh());

        $this->assertSame($fummo->getKey(), $sua->fresh()->employee_id, 'L\'agente dipendente non passa per la specializzazione.');
        $this->assertNotSame($fummo->getKey(), $altrui->fresh()->employee_id);
    }

    public function test_the_agent_is_matched_by_tax_code_before_the_name(): void
    {
        $dipendente = Employee::query()->create(['name' => 'Rossi Mario', 'is_active' => true, 'cf' => 'RSSMRA80A01H501U']);
        $agente = Fornitore::query()->create(['name' => 'Altro Nome', 'piva' => '99900000023', 'cf' => 'RSSMRA80A01H501U', 'is_active' => true]);
        $pratica = $this->pratica(null);
        $pratica->update(['fornitori_id' => $agente->getKey()]);

        app(AssegnaIstruttore::class)->assegna($pratica->fresh());

        $this->assertSame($dipendente->getKey(), $pratica->fresh()->employee_id);
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\Istruttori\Pages\ListIstruttori;
use App\Models\User;
use App\Services\Istruttoria\IstruttoreImpersonationService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Employee;
use Unico\Core\Models\TipoProdotto;

class IstruttoriImpersonationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function istruttore(string $nome, bool $conUtente = true, string $ruolo = 'istruttore'): Employee
    {
        $utente = $conUtente ? User::factory()->create(['role' => 'employee']) : null;

        return Employee::query()->create(['name' => $nome, 'employee_types' => 'dipendente', 'employee_roles' => [$ruolo], 'user_id' => $utente?->id, 'is_active' => true]);
    }

    public function test_an_administrator_lists_the_instructors_and_enters_as_one_of_them(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin);
        $istruttore = $this->istruttore('Istruttore Uno');
        $senzaUtente = $this->istruttore('Istruttore Due', false);
        $altro = $this->istruttore('Commerciale Tre', true, 'commerciale');

        Livewire::test(ListIstruttori::class)
            ->assertCanSeeTableRecords([$istruttore, $senzaUtente])
            ->assertCanNotSeeTableRecords([$altro])
            ->assertActionVisible(TestAction::make('specializzazione')->table($istruttore))
            ->assertActionVisible(TestAction::make('impersonateIstruttore')->table($istruttore))
            ->assertActionHidden(TestAction::make('impersonateIstruttore')->table($senzaUtente))
            ->callAction(TestAction::make('impersonateIstruttore')->table($istruttore));

        $this->assertSame($istruttore->user_id, auth()->id());
        $this->assertTrue(app(IstruttoreImpersonationService::class)->isImpersonating());
        $this->assertSame('Istruttore Uno', app(IstruttoreImpersonationService::class)->nome());

        // La dashboard mostra il banner e «Da prendere in carico» filtra sul dipendente impersonificato.
        $this->get('/admin')->assertOk()->assertSee('Stai visualizzando il pannello come istruttore')->assertSee('Istruttore Uno');

        $this->post(route('istruttori.impersonation.leave'))->assertRedirect();

        $this->assertSame($admin->id, auth()->id());
        $this->assertFalse(app(IstruttoreImpersonationService::class)->isImpersonating());
    }

    public function test_an_instructor_without_a_user_gets_one_created_instead_of_the_impersonate_button(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $conUtente = $this->istruttore('Istruttore Uno');
        $senzaUtente = $this->istruttore('Istruttore Due', false);
        $senzaUtente->update(['email' => 'Due@Races.it']);

        Livewire::test(ListIstruttori::class)
            ->assertActionHidden(TestAction::make('creaUtenteIstruttore')->table($conUtente))
            ->assertActionVisible(TestAction::make('creaUtenteIstruttore')->table($senzaUtente))
            ->callAction(TestAction::make('creaUtenteIstruttore')->table($senzaUtente));

        $utente = User::query()->where('email', 'due@races.it')->firstOrFail();
        $this->assertSame($utente->id, $senzaUtente->fresh()->user_id);
        $this->assertSame('employee', $utente->role);
        $this->assertTrue(app(IstruttoreImpersonationService::class)->canImpersonate(auth()->user(), $senzaUtente->fresh()));
    }

    public function test_only_administrators_can_impersonate_and_not_themselves(): void
    {
        $istruttore = $this->istruttore('Istruttore Uno');
        $servizio = app(IstruttoreImpersonationService::class);

        $this->assertFalse($servizio->canImpersonate(User::factory()->create(['role' => 'employee']), $istruttore));
        $this->assertFalse($servizio->canImpersonate(null, $istruttore));
        $this->assertFalse($servizio->canImpersonate(User::query()->find($istruttore->user_id), $istruttore));
        $this->assertTrue($servizio->canImpersonate(User::factory()->create(['role' => 'admin']), $istruttore));

        $this->post(route('istruttori.impersonation.leave'))->assertForbidden();
    }

    public function test_an_administrator_saves_the_specialization_of_an_instructor(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $istruttore = $this->istruttore('Istruttore Uno');
        $prodotto = TipoProdotto::query()->create(['name' => 'Prestito ZZ', 'is_active' => true]);
        $banca = Cliente::query()->create(['name' => 'CAPITALFIN ZZ', 'is_active' => true]);

        Livewire::test(ListIstruttori::class)
            ->callAction(TestAction::make('specializzazione')->table($istruttore), ['tipoprodotto_ids' => [$prodotto->getKey()], 'clienti_ids' => [$banca->getKey()]])
            ->assertHasNoFormErrors();

        $this->assertSame([$prodotto->getKey()], $istruttore->fresh()->tipoprodotto_ids);
        $this->assertSame([$banca->getKey()], $istruttore->fresh()->clienti_ids);
    }
}

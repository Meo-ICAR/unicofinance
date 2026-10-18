<?php

namespace Tests\Feature;

use App\Filament\Agenti\Pages\ManualeUtente as ManualeUtenteAgenti;
use App\Filament\Pages\ManualeTecnico;
use App\Filament\Pages\ManualeUtente;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Fornitore;

class ManualiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_guest_is_sent_to_the_admin_login(): void
    {
        $this->get(route('manuali.utente'))->assertRedirect(route('filament.admin.auth.login'));
        $this->get(route('manuali.tecnico'))->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_staff_opens_both_manuals_in_the_admin_panel(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'employee']));

        $this->get(route('manuali.utente'))->assertOk()->assertSee('Documenti di cessione e delega', false);
        $this->get(route('manuali.tecnico'))->assertOk()->assertSee('Non ancora automatico', false);
        $this->get('/admin/manuale-utente')->assertOk()->assertSee('Manuale utente');
        $this->get('/admin/manuale-tecnico')->assertOk()->assertSee('Manuale tecnico');

        Livewire::test(ManualeUtente::class)->assertOk()->assertSee('Apri per la stampa');
        Livewire::test(ManualeTecnico::class)->assertOk()->assertSee('Apri per la stampa');
    }

    public function test_an_agent_opens_the_user_manual_and_not_the_technical_one(): void
    {
        Filament::setCurrentPanel('agenti');
        $user = User::factory()->create(['role' => 'fornitore']);
        Fornitore::query()->create([
            'name' => 'Agente Manuale',
            'piva' => '994'.random_int(10000000, 99999999),
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $this->actingAs($user);

        $this->get(route('manuali.utente'))->assertOk()->assertSee('Portale agenti', false);
        $this->get(route('manuali.tecnico'))->assertForbidden();
        $this->get('/agenti/manuale-utente')->assertOk()->assertSee('Manuale utente');
        $this->get('/agenti/manuale-tecnico')->assertNotFound();

        Livewire::test(ManualeUtenteAgenti::class)->assertOk()->assertSee('Apri per la stampa');
    }

    public function test_a_signed_in_user_without_a_panel_cannot_read_the_manuals(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'client']));

        $this->get(route('manuali.utente'))->assertForbidden();
        $this->get(route('manuali.tecnico'))->assertForbidden();
    }
}

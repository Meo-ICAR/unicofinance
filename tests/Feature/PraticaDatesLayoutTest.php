<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Pratica;

class PraticaDatesLayoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_the_workflow_dates_are_saved_from_the_admin_form(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZDATE-'.Str::upper(Str::random(4)), 'upload_at' => now()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertSee('Data presa in carico')
            ->assertSee('Data lavorazione')
            ->assertSee('Data invio istruttoria')
            ->fillForm(['presa_in_carico_at' => '2026-03-02', 'lavorazione_at' => '2026-03-03', 'sended_at' => '2026-03-10'])
            ->call('save')
            ->assertHasNoFormErrors();

        $pratica->refresh();
        $this->assertSame('2026-03-02', $pratica->presa_in_carico_at->toDateString());
        $this->assertSame('2026-03-03', $pratica->lavorazione_at->toDateString());
        $this->assertSame('2026-03-10', $pratica->sended_at->toDateString());
    }

    public function test_the_client_edit_form_renders_its_sections(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $client = Client::query()->create(['name' => 'Rossi', 'is_person' => true]);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertSee('Contatti')
            ->assertSee('Dati anagrafici');
    }
}

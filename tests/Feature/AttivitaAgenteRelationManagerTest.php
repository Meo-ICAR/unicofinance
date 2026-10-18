<?php

namespace Tests\Feature;

use App\Filament\RelationManagers\AttivitaAgenteRelationManager;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\Pratica;
use App\Models\User;
use App\Services\Agenti\AgentRequestDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttivitaAgenteRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_the_istruttore_sees_what_unicoagent_sent_and_the_tab_is_hidden_otherwise(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $pratica = Pratica::query()->create(['codice_pratica' => 'WA-ZZ-NOTE', 'cognome_cliente' => 'Rossi', 'codice_fiscale' => 'RSSMRA80A01H501U', 'upload_at' => now()]);
        $senza = Pratica::query()->create(['codice_pratica' => 'ZZ-SENZA', 'cognome_cliente' => 'Bianchi', 'upload_at' => now()]);
        $details = app(AgentRequestDetails::class);
        $details->log($pratica, 'richiesta', 'Richiesta consegnata da unicoagent', ['importo_fascia' => '15-20 mila', 'durata_mesi' => 120, 'agente' => ['nome' => 'ZZ AGENTE', 'sigla' => 'ZZA'], 'documenti' => [['nome' => 'Busta paga', 'obbligo' => 'obbligatorio']]]);
        $details->log($pratica, 'nota', 'Chiamare dopo le 18', ['autore' => 'agente']);

        Livewire::test(AttivitaAgenteRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class])
            ->assertCanSeeTableRecords($pratica->attivitaAgente()->get())
            ->assertSee('Chiamare dopo le 18')
            ->assertSee('Importo: 15-20 mila')
            ->assertSee('Agente: ZZ AGENTE · sigla ZZA')
            ->assertSee('Documenti attesi: Busta paga (obbligatorio)');

        $this->assertTrue(AttivitaAgenteRelationManager::canViewForRecord($pratica, EditPratica::class));
        $this->assertFalse(AttivitaAgenteRelationManager::canViewForRecord($senza, EditPratica::class));
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Agenti\Pages\Simulazione;
use App\Models\FinancingQuote;
use App\Models\Pratica;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\PraticaStato;

/**
 * Un agente vede solo i dati propri: cambiando un id nell'indirizzo o nei dati della pagina non arriva a quelli di un altro agente.
 */
class AgentIsolationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core', 'proforma'];

    private function agent(string $name): Fornitore
    {
        $user = User::factory()->create(['role' => 'fornitore']);

        return Fornitore::query()->create(['name' => $name, 'piva' => random_int(900, 999).random_int(10000000, 99999999), 'user_id' => $user->id, 'is_active' => true]);
    }

    private function documentOf(Fornitore $agent): Document
    {
        $pratica = Pratica::query()->create(['codice_pratica' => 'ISO-'.uniqid(), 'cognome_cliente' => 'Cliente di '.$agent->name, 'fornitori_id' => $agent->getKey(), 'upload_at' => now()]);
        $this->collegaProvvigioneProduttore($agent, $pratica);

        return Document::query()->create([
            'documentable_type' => 'pratica', 'documentable_id' => (string) $pratica->getKey(),
            'document_type_id' => DocumentType::query()->create(['name' => 'Busta paga', 'code' => 'ISO-'.uniqid()])->getKey(),
            'name' => 'Busta paga', 'status' => DocumentStatus::UPLOADED,
        ]);
    }

    public function test_an_agent_cannot_download_the_documents_of_another_agents_practices(): void
    {
        $mine = $this->agent('Agente A');
        $other = $this->agent('Agente B');
        $own = $this->documentOf($mine);
        $foreign = $this->documentOf($other);

        $this->actingAs(User::query()->findOrFail($mine->user_id));

        $this->get(route('documents.download', $foreign))->assertForbidden();
        $this->assertNotSame(403, $this->get(route('documents.download', $own))->getStatusCode());
    }

    public function test_employees_and_the_other_staff_can_read_the_documents_of_producers_and_their_practices(): void
    {
        $agent = $this->agent('Agente B');
        $ofPractice = $this->documentOf($agent);
        $ofProducer = Document::query()->create([
            'documentable_type' => 'fornitore', 'documentable_id' => (string) $agent->getKey(),
            'document_type_id' => DocumentType::query()->create(['name' => 'Contratto agente', 'code' => 'ISO-F-'.uniqid()])->getKey(),
            'name' => 'Contratto agente', 'status' => DocumentStatus::UPLOADED,
        ]);

        foreach (['employee', 'inspector', 'admin', 'super_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));

            $this->assertNotSame(403, $this->get(route('documents.download', $ofPractice))->getStatusCode(), $role);
            $this->assertNotSame(403, $this->get(route('documents.download', $ofProducer))->getStatusCode(), $role);
        }
    }

    public function test_an_agent_reads_his_own_producer_documents_but_not_another_agents(): void
    {
        $mine = $this->agent('Agente A');
        $other = $this->agent('Agente B');
        $document = fn (Fornitore $agent): Document => Document::query()->create([
            'documentable_type' => 'fornitore', 'documentable_id' => (string) $agent->getKey(),
            'document_type_id' => DocumentType::query()->create(['name' => 'Contratto agente', 'code' => 'ISO-F-'.uniqid()])->getKey(),
            'name' => 'Contratto agente', 'status' => DocumentStatus::UPLOADED,
        ]);

        $this->actingAs(User::query()->findOrFail($mine->user_id));

        $this->assertNotSame(403, $this->get(route('documents.download', $document($mine)))->getStatusCode());
        $this->get(route('documents.download', $document($other)))->assertForbidden();
    }

    public function test_the_quote_of_the_simulation_cannot_be_swapped_with_another_agents_one(): void
    {
        Filament::setCurrentPanel('agenti');
        $mine = $this->agent('Agente A');
        $other = $this->agent('Agente B');
        $foreign = FinancingQuote::query()->create([
            'fornitore_id' => $other->getKey(), 'number' => 1, 'contract_type' => 'cessione', 'sex' => 'M', 'birth_date' => '1980-01-01',
            'employment_relationship' => 'privato_spa', 'hired_at' => '2010-01-01', 'starts_at' => now()->toDateString(), 'duration_months' => 120,
            'assignable_quota' => 300, 'age_at_start' => 46, 'seniority_months' => 200, 'disbursed_min' => 20000, 'disbursed_max' => 25000,
            'status' => 'salvato', 'pratica_id' => Pratica::query()->create(['codice_pratica' => 'ISO-Q-'.uniqid(), 'fornitori_id' => $other->getKey(), 'pratica_stato_id' => PraticaStato::query()->firstOrCreate(['codice' => 'PREVENTIVO', 'tipo_finanziamento' => 'Cessione'], ['name' => 'PREVENTIVO', 'ordine' => 0, 'is_working' => true, 'colore' => 'gray'])->getKey(), 'upload_at' => now()])->getKey(),
        ]);

        $this->actingAs(User::query()->findOrFail($mine->user_id));

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(Simulazione::class)->set('quoteId', $foreign->getKey());
    }
}

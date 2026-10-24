<?php

namespace Tests\Feature;

use App\Enums\ApiCallStatus;
use App\Enums\ApiProvider;
use App\Filament\Pages\AssistenteAi;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\ApiCall;
use App\Models\Pratica;
use App\Models\User;
use App\Services\Istruttoria\ContestoAssistente;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Testing\FakeAIProvider;
use Tests\TestCase;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentRequirement;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Task;
use Unico\Core\Models\TipoProdotto;

class AssistenteIstruttoriaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_a_missing_required_document_appears_in_the_pratica_context(): void
    {
        $prodotto = TipoProdotto::query()->create(['name' => 'Prodotto assistente', 'tipo_prodotto' => 'Prodotto assistente']);
        $identita = DocumentType::query()->create(['name' => 'Identità assistente', 'code' => 'AI-ID']);
        $reddito = DocumentType::query()->create(['name' => 'Reddito assistente', 'code' => 'AI-RD']);
        $task = Task::query()->create([
            'name' => 'Check list assistente',
            'taskable' => 'pratica',
            'trigger_field' => 'tipo_prodotto',
            'trigger_value' => 'Prodotto assistente',
            'tipoprodotto_id' => $prodotto->getKey(),
            'is_active' => true,
        ]);

        foreach ([$identita, $reddito] as $ordine => $tipo) {
            DocumentRequirement::query()->create([
                'task_id' => $task->getKey(),
                'document_type_id' => $tipo->getKey(),
                'is_required' => true,
                'sort_order' => $ordine + 1,
            ]);
        }

        $pratica = Pratica::query()->create([
            'codice_pratica' => 'ZZCTX-1',
            'cognome_cliente' => 'Rossi',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'tipoprodotto_id' => $prodotto->getKey(),
            'upload_at' => now(),
        ]);
        Document::query()->create([
            'documentable_type' => 'pratica',
            'documentable_id' => (string) $pratica->getKey(),
            'document_type_id' => $identita->getKey(),
            'name' => 'Identità assistente',
            'status' => DocumentStatus::UPLOADED,
        ]);

        $contesto = app(ContestoAssistente::class)->pratica($pratica);

        $this->assertStringContainsString('Mancano: Reddito assistente.', $contesto);
        $this->assertStringNotContainsString('Identità assistente', $contesto);
        $this->assertStringNotContainsString('RSSMRA80A01H501U', $contesto);
    }

    public function test_a_non_staff_user_does_not_see_the_assistant_action(): void
    {
        Filament::setCurrentPanel('admin');
        $pratica = Pratica::query()->create([
            'codice_pratica' => 'ZZVIS-1',
            'cognome_cliente' => 'Bianchi',
            'upload_at' => now(),
        ]);

        $this->actingAs(User::factory()->create(['role' => 'fornitore']));
        $this->assertFalse(AssistenteAi::canAccess());
        $pagineAgenti = array_map(
            fn (string $classe): string => class_basename($classe),
            Filament::getPanel('agenti')->getPages(),
        );
        $this->assertNotContains('AssistenteAi', $pagineAgenti);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertActionHidden(TestAction::make('chiediAssistente'));

        $this->actingAs(User::factory()->create(['role' => 'employee']));
        $this->assertTrue(AssistenteAi::canAccess());
        $this->assertContains(AssistenteAi::class, Filament::getPanel('admin')->getPages());

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertActionVisible(TestAction::make('chiediAssistente'));
    }

    public function test_the_assistant_call_is_logged_without_network_and_does_not_change_the_pratica(): void
    {
        Http::preventStrayRequests();
        config(['services.anthropic.cost' => 1.25, 'services.anthropic.key' => 'test-key', 'services.anthropic.model' => 'claude-test']);
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'employee']));
        $pratica = Pratica::query()->create([
            'codice_pratica' => 'ZZLOG-1',
            'cognome_cliente' => 'Verdi',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'upload_at' => now(),
        ]);
        $this->app->instance(AIProviderInterface::class, new FakeAIProvider(
            new AssistantMessage('Manca il reddito, usa Cambia stato in scheda.'),
        ));

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->callAction(TestAction::make('chiediAssistente'), ['domanda' => 'Cosa manca per RSSMRA80A01H501U?'])
            ->assertNotified();

        $pratica->refresh();
        $this->assertNull($pratica->pratica_stato_id);
        $this->assertSame(0, $pratica->praticaStatusHistory()->count());

        $chiamata = ApiCall::query()->where('operation', 'istruttore.assistente')->where('provider', ApiProvider::Anthropic)->first();
        $this->assertNotNull($chiamata);
        $this->assertSame(ApiCallStatus::Success, $chiamata->status, (string) $chiamata->error);
        $this->assertSame((string) $pratica->getKey(), (string) $chiamata->subject_id);
        $this->assertSame('pratica', $chiamata->subject_type);
        $this->assertSame('1.2500', (string) $chiamata->cost);
        $this->assertStringNotContainsString('RSSMRA80A01H501U', json_encode($chiamata->only(['request', 'response', 'summary', 'error'])));
    }

    public function test_a_missing_api_key_shows_an_error_and_keeps_the_page(): void
    {
        Http::preventStrayRequests();
        config(['services.anthropic.key' => null]);
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'employee']));

        Livewire::test(AssistenteAi::class)
            ->fillForm(['prompt' => 'Quali pratiche sono in attesa?'])
            ->call('send')
            ->assertSee('Chiave API Anthropic mancante: imposta ANTHROPIC_API_KEY nel file .env')
            ->assertSee('Chiedi all\'assistente');
    }

    public function test_the_assistant_reads_both_manuals_without_the_generated_appendix(): void
    {
        $manuale = app(ContestoAssistente::class)->manuale();

        $this->assertStringContainsString('Manuale utente:', $manuale);
        $this->assertStringContainsString('Manuale tecnico:', $manuale);
        $this->assertStringContainsString('Assegnazione automatica', $manuale);
        $this->assertStringContainsString('Istruttori e assegnazione automatica', $manuale);
        $this->assertStringNotContainsString('Sezione generata da', $manuale);
    }
}

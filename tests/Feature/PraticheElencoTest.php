<?php

namespace Tests\Feature;

use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Filament\Resources\Praticas\Pages\ListPraticas;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaStato;

class PraticheElencoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function stato(string $name, array $flags = []): PraticaStato
    {
        return PraticaStato::query()->firstOrCreate(['codice' => $name, 'tipo_finanziamento' => 'Cessione'], ['name' => $name, 'ordine' => 0, 'is_working' => true, 'is_estingued' => false, 'colore' => 'gray'] + $flags);
    }

    public function test_suspended_practices_are_hidden_by_default_and_the_ternary_filter_shows_them(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $attiva = Pratica::query()->create(['codice_pratica' => 'ELE-ATTIVA', 'pratica_stato_id' => $this->stato('Inserita')->getKey(), 'upload_at' => now()]);
        $sospesa = Pratica::query()->create(['codice_pratica' => 'ELE-SOSPESA', 'pratica_stato_id' => $this->stato('SOSPESA-TEST', ['is_suspended' => true])->getKey(), 'upload_at' => now()]);

        Livewire::test(ListPraticas::class)
            ->assertCanSeeTableRecords([$attiva])
            ->assertCanNotSeeTableRecords([$sospesa])
            ->filterTable('sospese', true)
            ->assertCanSeeTableRecords([$sospesa])
            ->assertCanNotSeeTableRecords([$attiva])
            ->removeTableFilter('sospese')
            ->assertCanSeeTableRecords([$attiva, $sospesa]);
    }

    public function test_the_state_can_be_changed_from_the_table_with_history_and_dates(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $da = $this->stato('Inserita');
        $approvata = $this->stato('APPROVATA-TEST', ['is_approved' => true]);
        $pratica = Pratica::query()->create(['codice_pratica' => 'ELE-CAMBIO', 'pratica_stato_id' => $da->getKey(), 'upload_at' => now()]);

        Livewire::test(ListPraticas::class)
            ->callTableAction('cambiaStato', $pratica, data: ['stato' => $approvata->getKey(), 'motivazione' => 'Aggiornato dal portale della banca'])
            ->assertHasNoTableActionErrors();

        $pratica->refresh();
        $this->assertSame($approvata->getKey(), $pratica->pratica_stato_id);
        $this->assertTrue($pratica->approved_at->isToday());
        $history = $pratica->praticaStatusHistory()->firstOrFail();
        $this->assertSame('Aggiornato dal portale della banca', $history->notes);
        $this->assertSame('APPROVATA-TEST', $history->status_to);
    }

    public function test_the_bank_practice_code_is_saved_and_searchable(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $pratica = Pratica::query()->create(['codice_pratica' => 'ELE-BANCA', 'upload_at' => now()]);
        $altra = Pratica::query()->create(['codice_pratica' => 'ELE-ALTRA', 'upload_at' => now()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->fillForm(['codice_pratica_banca' => 'BNK-998877'])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(ListPraticas::class)
            ->searchTable('BNK-998877')
            ->assertCanSeeTableRecords([$pratica])
            ->assertCanNotSeeTableRecords([$altra]);
    }
}

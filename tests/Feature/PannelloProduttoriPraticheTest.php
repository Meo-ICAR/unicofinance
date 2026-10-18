<?php

namespace Tests\Feature;

use App\Filament\Agenti\Resources\Pratiche\PraticaResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;

class PannelloProduttoriPraticheTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core', 'proforma'];

    public function test_admin_configuration_is_collapsed_and_ordered_and_hidden_from_producers(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $configurazione = collect(Filament::getPanel('admin')->getNavigation())
            ->first(fn (NavigationGroup $group): bool => $group->getLabel() === 'Configurazione');

        $this->assertInstanceOf(NavigationGroup::class, $configurazione);
        $this->assertTrue($configurazione->isCollapsed());
        $this->assertSame([
            'Produttori',
            'Listini provvigionali',
            'Istituti',
            'Provvigioni attive',
            'Istruttori',
            'Registro chiamate API',
            'Ruoli e permessi',
        ], collect($configurazione->getItems())->map(fn ($item) => $item->getLabel())->values()->all());

    }

    /** Test a parte: nello stesso test la navigazione costruita per l'admin resta in memoria e il pannello agenti ne erediterebbe le voci. */
    public function test_admin_configuration_is_hidden_from_producers(): void
    {
        $agent = User::factory()->create(['role' => 'fornitore']);
        Fornitore::query()->create(['name' => 'Produttore nav', 'piva' => '99123456789', 'user_id' => $agent->id, 'is_active' => true]);
        Filament::setCurrentPanel('agenti');
        $this->actingAs($agent);

        $voci = collect(Filament::getPanel('agenti')->getNavigation())
            ->flatMap(fn (NavigationGroup $group) => collect($group->getItems())->map(fn ($item) => $item->getLabel()));

        $this->assertFalse($voci->contains('Configurazione'));
        $this->assertFalse($voci->contains('Produttori'));
        $this->assertFalse($voci->contains('Listini provvigionali'));
        $this->assertFalse($voci->contains('Istituti'));
        $this->assertFalse($voci->contains('Provvigioni attive'));
        $this->assertFalse($voci->contains('Registro chiamate API'));
    }

    public function test_a_producer_sees_practices_with_his_commissions_or_linked_third_party_practices(): void
    {
        Filament::setCurrentPanel('agenti');
        $agent = User::factory()->create(['role' => 'fornitore']);
        $this->actingAs($agent);
        $fornitore = Fornitore::query()->create([
            'name' => 'Produttore visibile',
            'piva' => '12345678901',
            'user_id' => $agent->id,
            'is_active' => true,
        ]);
        $altro = Fornitore::query()->create(['name' => 'Altro produttore', 'piva' => '10987654321', 'is_active' => true]);

        $conProvvigione = Pratica::query()->create([
            'codice_pratica' => 'VIS-PROV',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'fornitori_id' => $fornitore->getKey(),
            'upload_at' => now(),
        ]);
        $this->collegaProvvigioneProduttore($fornitore, $conProvvigione);

        $senzaProvvigione = Pratica::query()->create([
            'codice_pratica' => 'VIS-NO',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'fornitori_id' => $fornitore->getKey(),
            'upload_at' => now(),
        ]);
        $terzi = Pratica::query()->create([
            'codice_pratica' => 'VIS-TERZI',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'is_notowned' => true,
            'fornitori_id' => $altro->getKey(),
            'upload_at' => now(),
        ]);
        $terziScollegati = Pratica::query()->create([
            'codice_pratica' => 'VIS-ALTRI',
            'codice_fiscale' => 'BNCGVN80A01H501Z',
            'is_notowned' => true,
            'upload_at' => now(),
        ]);
        $altrui = Pratica::query()->create([
            'codice_pratica' => 'VIS-ALTRUI',
            'fornitori_id' => $altro->getKey(),
            'upload_at' => now(),
        ]);
        $this->collegaProvvigioneProduttore($altro, $altrui);

        $ids = PraticaResource::getEloquentQuery()->pluck('id')->all();

        $this->assertEqualsCanonicalizing(
            [$conProvvigione->getKey(), $terzi->getKey()],
            $ids,
        );
        $this->assertNotContains($senzaProvvigione->getKey(), $ids);
        $this->assertNotContains($terziScollegati->getKey(), $ids);
        $this->assertNotContains($altrui->getKey(), $ids);

        $this->actingAs(User::factory()->create(['role' => 'fornitore']));

        $this->assertSame([], PraticaResource::getEloquentQuery()->pluck('id')->all());
    }
}

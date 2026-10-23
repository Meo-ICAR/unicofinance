<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Enums\IbanHolder;
use Unico\Core\Enums\Kinship;
use Unico\Core\Enums\MaritalStatus;
use Unico\Core\Enums\PaymentMethod;
use Unico\Core\Models\HiringType;
use Unico\Core\Models\IncomeType;
use Unico\Core\Models\OccupationType;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaClient;

class InterviewPreliminareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
    }

    public function test_le_anagrafiche_dell_intervista_ci_sono(): void
    {
        $this->assertSame(['Tempo indeterminato', 'Tempo determinato'], HiringType::orderBy('sort_order')->pluck('name')->all());
        $this->assertSame(['Netto', 'Lordo'], IncomeType::orderBy('sort_order')->pluck('name')->all());
        $this->assertSame(18, OccupationType::count());
        $this->assertDatabaseHas('address_types', ['name' => 'Storico'], 'core');
        $this->assertDatabaseHas('document_types', ['code' => 'PASSAPORTO'], 'core');
        $this->assertDatabaseHas('document_types', ['code' => 'TESSERA_MINISTERIALE'], 'core');
    }

    public function test_si_compilano_e_si_salvano_i_dati_dell_intervista_dalla_scheda_cliente(): void
    {
        $persona = Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);
        $occupazione = OccupationType::where('name', 'Operaio')->firstOrFail();
        $assunzione = HiringType::where('name', 'Tempo indeterminato')->firstOrFail();
        $reddito = IncomeType::where('name', 'Netto')->firstOrFail();

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->fillForm([
                'marital_status' => MaritalStatus::ConiugatoComunione->value, 'family_members' => 4, 'family_members_with_income' => 2,
                'occupation_type_id' => $occupazione->getKey(), 'hiring_type_id' => $assunzione->getKey(), 'income_type_id' => $reddito->getKey(),
                'salary_months' => 13, 'salary_day' => 27,
                'payment_method' => PaymentMethod::Sdd->value, 'iban_holder' => IbanHolder::Richiedente->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $client = $persona->fresh();
        $this->assertSame(MaritalStatus::ConiugatoComunione, $client->marital_status);
        $this->assertSame([4, 2, 13, 27], [$client->family_members, $client->family_members_with_income, $client->salary_months, $client->salary_day]);
        $this->assertSame('Operaio', $client->occupationType->name);
        $this->assertSame('Tempo indeterminato', $client->hiringType->name);
        $this->assertSame('Netto', $client->incomeType->name);
        $this->assertSame([PaymentMethod::Sdd, IbanHolder::Richiedente], [$client->payment_method, $client->iban_holder]);
    }

    public function test_giorno_dello_stipendio_e_mensilita_hanno_dei_limiti(): void
    {
        $persona = Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);

        Livewire::test(EditClient::class, ['record' => $persona->getKey()])
            ->fillForm(['salary_day' => 32, 'salary_months' => 0])
            ->call('save')
            ->assertHasFormErrors(['salary_day', 'salary_months']);
    }

    public function test_il_grado_di_parentela_del_coobbligato_e_la_finalita_della_pratica_si_salvano(): void
    {
        $richiedente = Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);
        $coobbligato = Client::query()->create(['name' => 'Verdi', 'tax_code' => 'VRDLGI85M01H501Z', 'is_person' => true]);
        $pratica = Pratica::query()->create(['codice_pratica' => 'INT-1', 'codice_fiscale' => $richiedente->tax_code, 'loan_purpose' => 'Ristrutturazione casa', 'upload_at' => now()]);

        $legame = PraticaClient::query()->create(['pratica_id' => $pratica->getKey(), 'client_id' => $coobbligato->getKey(), 'role' => 'coobbligato', 'kinship' => Kinship::Coniuge]);

        $this->assertSame(Kinship::Coniuge, $legame->fresh()->kinship);
        $this->assertSame('Ristrutturazione casa', $pratica->fresh()->loan_purpose);
    }
}

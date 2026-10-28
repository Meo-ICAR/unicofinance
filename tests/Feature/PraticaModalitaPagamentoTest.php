<?php

namespace Tests\Feature;

use App\Models\Pratica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Unico\Core\Enums\DisbursementMethod;
use Unico\Core\Enums\PaymentMethod;
use Unico\Core\Enums\PeriodicCommunication;

class PraticaModalitaPagamentoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_the_practice_keeps_how_the_installments_are_paid_how_the_loan_is_disbursed_and_how_the_bank_writes(): void
    {
        $pratica = Pratica::query()->create([
            'codice_pratica' => 'QT-MOD-1', 'upload_at' => now(),
            'repayment_method' => 'trattenuta', 'disbursement_method' => 'bonifico', 'periodic_communication' => 'elettronica',
        ])->fresh();

        $this->assertSame(PaymentMethod::Trattenuta, $pratica->repayment_method);
        $this->assertSame('Trattenuta sullo stipendio / pensione', $pratica->repayment_method->getLabel());
        $this->assertSame(DisbursementMethod::Bonifico, $pratica->disbursement_method);
        $this->assertSame(PeriodicCommunication::Elettronica, $pratica->periodic_communication);
        $this->assertSame('Elettronica (email)', $pratica->periodic_communication->getLabel());
    }

    public function test_the_modes_are_optional(): void
    {
        $pratica = Pratica::query()->create(['codice_pratica' => 'QT-MOD-2', 'upload_at' => now()])->fresh();

        $this->assertNull($pratica->repayment_method);
        $this->assertNull($pratica->disbursement_method);
        $this->assertNull($pratica->periodic_communication);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientRelation;
use App\Services\ContattiSocieta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContattiSocietaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_communications_go_to_the_referent_then_the_legal_representative_then_the_company(): void
    {
        $societa = Client::query()->create(['name' => 'Acme Srl', 'tax_code' => '12345678903', 'is_person' => false, 'email' => 'info@acme.test', 'phone' => '0810000000']);
        $legale = Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true, 'email' => 'rossi@acme.test', 'phone' => '3331111111']);
        $referente = Client::query()->create(['name' => 'Bianchi', 'tax_code' => 'BNCNNA82C45H501Y', 'is_person' => true, 'email' => 'commercialista@studio.test', 'phone' => '3332222222']);
        $servizio = app(ContattiSocieta::class);

        $this->assertSame('società', $servizio->comunicazioni($societa)['fonte']);

        ClientRelation::query()->create(['company_id' => $societa->getKey(), 'client_id' => $legale->getKey(), 'role' => 'legale_rappresentante']);
        $this->assertSame('legale rappresentante', $servizio->comunicazioni($societa)['fonte']);
        $this->assertSame('rossi@acme.test', $servizio->comunicazioni($societa)['email']);

        ClientRelation::query()->create(['company_id' => $societa->getKey(), 'client_id' => $referente->getKey(), 'role' => 'referente']);
        $contatto = $servizio->comunicazioni($societa);
        $this->assertSame('referente', $contatto['fonte']);
        $this->assertSame('commercialista@studio.test', $contatto['email']);

        // La firma resta sempre al legale rappresentante.
        $this->assertTrue($servizio->firma($societa)->is($legale));
    }
}

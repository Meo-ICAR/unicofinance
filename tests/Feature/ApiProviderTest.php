<?php

namespace Tests\Feature;

use App\Enums\ApiProvider;
use App\Models\ApiCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiProviderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_calls_written_by_unicocoge_are_cast_to_their_provider(): void
    {
        foreach ([
            'mediafacile' => [ApiProvider::Mediafacile, 'Mediafacile (import pratiche e provvigioni)'],
            'business_central' => [ApiProvider::BusinessCentral, 'Business Central (invio COGE)'],
        ] as $value => [$provider, $label]) {
            $call = ApiCall::query()->create(['provider' => $value, 'operation' => 'test', 'status' => 'success']);

            $call = $call->fresh();

            $this->assertSame($provider, $call->provider);
            $this->assertSame($label, $call->provider->getLabel());
            $this->assertSame(0.0, $provider->unitCost());
        }
    }

    public function test_every_provider_has_a_label_and_a_cost(): void
    {
        foreach (ApiProvider::cases() as $provider) {
            $this->assertNotEmpty($provider->getLabel());
            $this->assertIsFloat($provider->unitCost());
        }
    }
}

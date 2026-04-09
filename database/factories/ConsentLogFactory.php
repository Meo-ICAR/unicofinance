<?php

namespace Database\Factories;

use App\Models\ConsentLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsentLog>
 */
class ConsentLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => \App\Models\Client::factory(),
            'ip_address' => $this->faker->ipv4(),
            'origin' => $this->faker->randomElement(['Landing Page Proprietaria', 'Facebook Lead Ads', 'Google Search', 'DEM Partner']),
            'marketing_consent' => $this->faker->boolean(80),
            'third_party_transfer_consent' => $this->faker->boolean(50),
            'created_at' => now(),
        ];
    }
}

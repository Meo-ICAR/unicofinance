<?php

namespace Database\Factories;

use App\Models\LeadTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadTransfer>
 */
class LeadTransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => \App\Models\Client::factory()->lead(),
            'purchaser_id' => \App\Models\Client::factory()->purchaser(),
            'price' => $this->faker->randomFloat(2, 5, 50),
            'transfer_method' => $this->faker->randomElement(['api_tls', 'sftp', 'encrypted_csv']),
            'transferred_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }
}

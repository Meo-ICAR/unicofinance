<?php

namespace Database\Factories;

use App\Models\LeadReturnLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadReturnLog>
 */
class LeadReturnLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => \App\Models\Company::factory(),
            'client_id' => \App\Models\Client::factory()->purchaser(),
            'lead_id' => \App\Models\Client::factory()->lead(),
            'status' => $this->faker->randomElement(['bounce', 'opt_out_requested', 'converted']),
            'reported_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }
}

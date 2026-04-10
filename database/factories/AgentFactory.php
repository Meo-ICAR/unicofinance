<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'fiscal_code' => strtoupper(fake()->bothify('RSSMRA########????')),
            'email_personal' => fake()->unique()->safeEmail(),
            'email_corporate' => null,
            'phone' => fake()->phoneNumber(),
            'oam_number' => null,
            'status' => Agent::STATUS_LEAD,
            'contract_path' => null,
        ];
    }

    /**
     * Indicate that the agent is in evaluation.
     */
    public function inValutazione(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Agent::STATUS_IN_VALUTAZIONE,
        ]);
    }

    /**
     * Indicate that the agent has been approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Agent::STATUS_APPROVATO,
        ]);
    }

    /**
     * Indicate that the agent is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Agent::STATUS_ATTIVO,
            'email_corporate' => fake()->companyEmail(),
        ]);
    }
}

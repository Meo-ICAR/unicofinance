<?php

namespace Database\Factories;

use App\Models\SuppressionList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuppressionList>
 */
class SuppressionListFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['email', 'phone']);
        $value = ($type === 'email') ? $this->faker->safeEmail() : $this->faker->phoneNumber();
        
        return [
            'hashed_identifier' => hash('sha256', $value),
            'identifier_type' => $type,
            'request_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'do_not_contact' => true,
        ];
    }
}

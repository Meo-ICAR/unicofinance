<?php

namespace Database\Factories;

use App\Models\PraticaLead;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PraticaLead> */
class PraticaLeadFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'pratica_id' => 1,
            'is_ok' => true,
            'stato' => 'OK',
            'iduu' => fake()->numerify('########'),
        ];
    }
}

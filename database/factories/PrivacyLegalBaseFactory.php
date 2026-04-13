<?php

namespace Database\Factories;

use App\Models\PrivacyLegalBase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PrivacyLegalBase>
 */
class PrivacyLegalBaseFactory extends Factory
{
    protected $model = PrivacyLegalBase::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(4, true),
            'reference_article' => $this->faker->word() . ' Art. ' . $this->faker->numberBetween(1, 99),
            'description' => $this->faker->paragraph(),
        ];
    }
}

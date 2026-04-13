<?php

namespace Database\Factories;

use App\Models\PrivacyDataType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PrivacyDataType>
 */
class PrivacyDataTypeFactory extends Factory
{
    protected $model = PrivacyDataType::class;

    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(),
            'name' => $this->faker->words(3, true),
            'category' => $this->faker->word(),
            'retention_years' => $this->faker->numberBetween(1, 10),
        ];
    }
}

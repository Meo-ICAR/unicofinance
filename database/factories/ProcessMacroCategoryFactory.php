<?php

namespace Database\Factories;

use App\Models\ProcessMacroCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProcessMacroCategory>
 */
class ProcessMacroCategoryFactory extends Factory
{
    protected $model = ProcessMacroCategory::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->lexify('MACRO???'),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'is_active' => true,
        ];
    }
}

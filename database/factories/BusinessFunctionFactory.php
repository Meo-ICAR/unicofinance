<?php

namespace Database\Factories;

use App\Models\BusinessFunction;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BusinessFunction>
 */
class BusinessFunctionFactory extends Factory
{
    protected $model = BusinessFunction::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => $this->faker->unique()->lexify('BF?????'),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
        ];
    }
}

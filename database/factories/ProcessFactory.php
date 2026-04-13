<?php

namespace Database\Factories;

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessMacroCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Process>
 */
class ProcessFactory extends Factory
{
    protected $model = Process::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'owner_function_id' => BusinessFunction::factory(),
            'process_macro_category_id' => ProcessMacroCategory::factory(),
            'code' => $this->faker->unique()->lexify('PROC-?????'),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'target_model' => \App\Models\Client::class,
            'is_active' => true,
            'version' => 1,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function version(int $version): static
    {
        return $this->state(fn (array $attributes) => [
            'version' => $version,
        ]);
    }

    public function forClient(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_model' => \App\Models\Client::class,
        ]);
    }

    public function forEmployee(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_model' => \App\Models\Employee::class,
        ]);
    }

    public function forAgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_model' => \App\Models\Agent::class,
        ]);
    }
}

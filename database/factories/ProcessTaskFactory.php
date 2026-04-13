<?php

namespace Database\Factories;

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProcessTask>
 */
class ProcessTaskFactory extends Factory
{
    protected $model = ProcessTask::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'process_id' => Process::factory(),
            'business_function_id' => BusinessFunction::factory(),
            'sequence_number' => $this->faker->numberBetween(1, 100),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
        ];
    }

    public function sequence(int $number): static
    {
        return $this->state(fn (array $attributes) => [
            'sequence_number' => $number,
        ]);
    }
}

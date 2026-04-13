<?php

namespace Database\Factories;

use App\Models\Checklist;
use App\Models\ProcessTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Checklist>
 */
class ChecklistFactory extends Factory
{
    protected $model = Checklist::class;

    public function definition(): array
    {
        return [
            'process_task_id' => ProcessTask::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'sort_order' => $this->faker->numberBetween(1, 100),
        ];
    }

    public function order(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $order,
        ]);
    }
}

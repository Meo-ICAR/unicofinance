<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Employee;
use App\Models\ProcessTask;
use App\Models\TaskExecution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskExecution>
 */
class TaskExecutionFactory extends Factory
{
    protected $model = TaskExecution::class;

    public function definition(): array
    {
        $targetType = $this->faker->randomElement([Client::class, Employee::class]);

        return [
            'process_task_id' => ProcessTask::factory(),
            'status' => 'todo',
            'target_type' => $targetType,
            'target_id' => $targetType === Client::class
                ? Client::factory()
                : Employee::factory(),
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
            'target_type' => Client::class,
            'target_id' => $client->id,
        ]);
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
            'target_type' => Employee::class,
            'target_id' => $employee->id,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }

    public function withDueDate($date): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $date,
        ]);
    }
}

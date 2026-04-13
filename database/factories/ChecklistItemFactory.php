<?php

namespace Database\Factories;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    protected $model = ChecklistItem::class;

    public function definition(): array
    {
        return [
            'checklist_id' => Checklist::factory(),
            'instruction' => $this->faker->sentence(),
            'is_mandatory' => false,
            'sort_order' => $this->faker->numberBetween(1, 100),
        ];
    }

    public function mandatory(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_mandatory' => true,
        ]);
    }

    public function withAction(string $actionClass): static
    {
        return $this->state(fn (array $attributes) => [
            'action_class' => $actionClass,
        ]);
    }

    public function withRequireCondition(string $conditionClass): static
    {
        return $this->state(fn (array $attributes) => [
            'require_condition_class' => $conditionClass,
        ]);
    }

    public function withSkipCondition(string $conditionClass): static
    {
        return $this->state(fn (array $attributes) => [
            'skip_condition_class' => $conditionClass,
        ]);
    }

    public function order(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $order,
        ]);
    }
}

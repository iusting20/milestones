<?php

namespace Database\Factories;

use App\Models\TaskLog;
use App\Models\TodoItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskLog>
 */
class TaskLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = now()->subHour();

        return [
            'todo_item_id' => TodoItem::query()->create([
                'date' => now()->toDateString(),
                'title' => fake()->sentence(3),
                'is_completed' => false,
                'position' => 1,
            ])->id,
            'title' => fake()->sentence(3),
            'started_at' => $startedAt,
            'finished_at' => $startedAt->copy()->addMinutes(fake()->numberBetween(5, 90)),
        ];
    }
}

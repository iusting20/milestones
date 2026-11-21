<?php

namespace Database\Factories;

use App\Models\RoadmapItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoadmapItem>
 */
class RoadmapItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'kind' => 'timeline',
            'started_on' => now()->toDateString(),
            'completed_on' => null,
        ];
    }

    public function deliverable(): static
    {
        return $this->state(fn (): array => [
            'kind' => 'deliverable',
            'finish_on' => null,
        ]);
    }

    public function completed(?string $on = null): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_on' => $on ?? $attributes['started_on'],
        ]);
    }
}

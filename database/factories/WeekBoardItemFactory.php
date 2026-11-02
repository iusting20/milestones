<?php

namespace Database\Factories;

use App\Enums\WeekBoardStatus;
use App\Models\Project;
use App\Models\WeekBoardItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeekBoardItem>
 */
class WeekBoardItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => fn () => Project::query()->create([
                'hub' => 'partners',
                'name' => $name = fake()->unique()->company(),
                'slug' => Project::uniqueSlug('partners', $name),
                'position' => 1,
            ])->id,
            'title' => fake()->sentence(3),
            'status' => WeekBoardStatus::Planned,
            'position' => 1,
        ];
    }
}

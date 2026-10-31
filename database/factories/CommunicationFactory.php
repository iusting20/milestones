<?php

namespace Database\Factories;

use App\Enums\CommunicationType;
use App\Models\Communication;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Communication>
 */
class CommunicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::query()->create([
                'hub' => 'partners',
                'name' => fake()->company(),
                'slug' => fake()->unique()->slug(),
                'position' => 1,
            ])->id,
            'type' => CommunicationType::QuickUpdate,
            'occurred_on' => now()->toDateString(),
        ];
    }
}

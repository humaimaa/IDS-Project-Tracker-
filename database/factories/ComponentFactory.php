<?php

namespace Database\Factories;

use App\Models\Component;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Component>
 */
class ComponentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => 'Test component',
            'sequence_no' => 1,
            'baseline_end' => '2026-10-01',
            'effective_end' => '2026-10-01',
            'lifecycle' => 'Ongoing',
        ];
    }
}

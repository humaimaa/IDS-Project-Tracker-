<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TrackerRecordFactory extends Factory
{
    public function definition(): array
    {
        return ['module' => 'projects', 'data' => [
            'name' => fake()->sentence(3), 'reference' => fake()->unique()->bothify('IDS-####'),
            'stage' => 'Concept', 'agency' => 'Health Department', 'status' => 'Active',
        ]];
    }
}

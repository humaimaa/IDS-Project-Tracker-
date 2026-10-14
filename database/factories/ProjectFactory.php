<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Project;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_code' => fake()->unique()->bothify('TEST-####-????'),
            'name' => fake()->sentence(4),
            'phase' => 'Ongoing',
            'lifecycle' => 'Active',
            'sector_id' => Sector::firstOrCreate(['code' => 'TEST'], ['name' => 'Test sector'])->id,
            'district_id' => District::firstOrCreate(['code' => 'TEST'], ['name' => 'Test district'])->id,
            'start_date' => '2026-01-01',
            'original_end_date' => '2027-06-30',
            'current_end_date' => '2027-06-30',
        ];
    }
}

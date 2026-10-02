<?php

namespace Database\Seeders;

use App\Models\TrackerRecord;
use Illuminate\Database\Seeder;

class TrackerRecordSeeder extends Seeder
{
    public function run(): void
    {
        TrackerRecord::factory()->count(5)->create();
    }
}

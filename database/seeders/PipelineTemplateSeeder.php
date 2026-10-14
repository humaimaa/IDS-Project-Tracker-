<?php

namespace Database\Seeders;

use App\Models\PipelineStepTemplate;
use Illuminate\Database\Seeder;

class PipelineTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (range(1, 5) as $order) {
            $template = PipelineStepTemplate::firstOrCreate(['sort_order' => $order], ['name' => 'Component '.$order, 'active' => true]);
            if ($template->name === 'Step '.$order) {
                $template->update(['name' => 'Component '.$order]);
            }
        }
    }
}

<?php

use App\Models\TrackerRecord;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('projects:sync-activities', function (): void {
    TrackerRecord::where('module', 'projects')->eachById(function (TrackerRecord $project): void {
        DB::transaction(function () use ($project): void {
            TrackerRecord::whereKey($project->id)->lockForUpdate()->firstOrFail()->createDefaultActivities();
        });
    });
    $this->info('Predefined project activities synchronized. Existing progress preserved.');
})->purpose('Add missing predefined activities to existing projects');

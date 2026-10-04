<?php

namespace App;

use App\Models\TrackerRecord;
use Illuminate\Support\Collection;

class ProjectPortfolio
{
    public function rows(): Collection
    {
        if (config('dashboard.presentation')) {
            return collect(config('dashboard.projects'))->map(fn (array $data): array => $this->row(new TrackerRecord(['module' => 'projects', 'data' => $data]), collect(), collect(), true));
        }
        $projects = TrackerRecord::where('module', 'projects')->latest('id')->get();
        $demo = $projects->isEmpty();
        if ($demo) {
            $projects = collect(config('dashboard.projects'))->map(fn (array $data): TrackerRecord => new TrackerRecord(['module' => 'projects', 'data' => $data]));
        }
        $work = TrackerRecord::whereIn('module', ['activities', 'subactivities'])->get()->groupBy('data.project_id');
        $issues = TrackerRecord::where('module', 'issues')->get();

        return $projects->map(fn (TrackerRecord $project): array => $this->row($project, $work->get((string) $project->id, collect()), $issues, $demo))->values();
    }

    public function row(TrackerRecord $project, Collection $work, Collection $issues, bool $demo = false): array
    {
        $data = $project->data;
        $phase = in_array($data['stage'] ?? '', ['Implementation', 'Ongoing']) ? 'Ongoing' : 'Pipeline';
        $reasons = collect();
        foreach ($work as $item) {
            $status = strtolower($item->data['status'] ?? '');
            if (! in_array($status, ['completed', 'not applicable', 'cancelled', 'canceled']) && empty($item->data['actual_completion']) && (in_array($status, ['delayed', 'issue']) || (! empty($item->data['due']) && $item->data['due'] < now('Asia/Karachi')->toDateString()))) {
                $reasons->push(($item->module === 'subactivities' ? 'Sub-component: ' : 'Component: ').($item->data['name'] ?? 'Unnamed').' is delayed');
            }
        }
        foreach ($issues as $issue) {
            $linked = isset($issue->data['project_id']) ? $project->exists && (string) $issue->data['project_id'] === (string) $project->id : ($issue->data['project'] ?? '') === ($data['name'] ?? '');
            if ($linked && ! in_array(strtolower($issue->data['status'] ?? 'open'), ['resolved', 'closed', 'cancelled'])) {
                $reasons->push('Custom issue: '.$issue->data['name']);
            }
        }
        if ($demo) {
            foreach ($data['issues'] ?? [] as $issue) {
                if (! in_array(strtolower($issue['status']), ['resolved', 'closed', 'cancelled'])) {
                    $reasons->push($issue['name']);
                }
            }
        }
        $completed = in_array(strtolower($data['status'] ?? ''), ['completed', 'cancelled', 'canceled']);
        $health = $reasons->isNotEmpty() ? 'Off track' : ($completed ? ucfirst(strtolower($data['status'])) : 'On track');

        return ['project' => $project, 'phase' => $phase, 'health' => $health, 'has_issues' => $reasons->isNotEmpty(), 'reasons' => $reasons->all(), 'demo' => $demo,
            'url' => $project->exists ? route('projects.overview', $project) : route('dashboard.projects.show', $data['reference'])];
    }

    public function components(TrackerRecord $project, string $kind): Collection
    {
        $seed = (int) sprintf('%u', crc32($project->data['reference'] ?? (string) $project->id));
        $pipeline = $kind === 'pipeline';
        $current = $pipeline ? (($project->data['health'] ?? '') === 'Delayed' ? 3 : ($seed % 5) + 1) : 0;
        $isPipeline = ! in_array($project->data['stage'] ?? '', ['Implementation', 'Ongoing']);
        $delayed = ! $project->exists && ($project->data['health'] ?? '') === 'Delayed';

        return collect(range(1, $pipeline ? 5 : 6))->map(function (int $number) use ($seed, $pipeline, $current, $isPipeline, $delayed): array {
            $progress = $pipeline ? (! $isPipeline || $number < $current ? 100 : ($number === $current ? 55 : 0)) : (($seed % 19 + $number * 13) % 101);
            $status = $progress === 100 ? 'Completed' : ($progress === 0 ? 'Not started' : 'Ongoing');
            if ($delayed && $number === ($pipeline ? 3 : 2)) {
                $status = 'Delayed';
                $progress = 35;
            }
            $subs = $pipeline ? [] : collect(range(1, 2 + ($seed + $number) % 4))->map(fn (int $sub): array => ['name' => 'Sub-component '.$number.'.'.$sub, 'progress' => min(100, max(0, $progress + ($sub - 2) * 8)), 'status' => $status, 'officer' => 'Implementation team'])->all();

            return ['name' => 'Component '.$number, 'progress' => $progress, 'status' => $status, 'current' => $pipeline && $isPipeline && $number === $current, 'start' => '2026-07-01', 'due' => $status === 'Delayed' ? '2026-09-30' : '2027-06-30', 'officer' => 'Project implementation unit', 'subcomponents' => $subs];
        });
    }

    public function finances(TrackerRecord $project): array
    {
        $seed = (int) sprintf('%u', crc32($project->data['reference'] ?? (string) $project->id));
        $cost = (float) ($project->data['cost'] ?? (500000000 + ($seed % 10) * 100000000));
        $years = [];
        foreach (['2024-25', '2025-26', '2026-27'] as $index => $year) {
            $quarters = [];
            foreach (range(1, 4) as $quarter) {
                $release = round($cost * (0.025 + $index * 0.005 + $quarter * 0.004), 2);
                $quarters[$quarter] = ['release' => $release, 'expenditure' => round($release * (0.6 + (($seed + $quarter + $index) % 30) / 100), 2)];
            }
            $years[$year] = ['foreign' => $cost * 0.18, 'local' => $cost * 0.06, 'target' => $cost * 0.2, 'achieved' => array_sum(array_column($quarters, 'release')), 'quarters' => $quarters];
        }
        $commitment = $cost * 0.8;
        $disbursed = array_sum(array_column($years, 'achieved'));

        return ['cost' => $cost, 'foreign' => $cost * 0.8, 'local' => $cost * 0.2, 'commitment' => $commitment, 'disbursed' => $disbursed, 'undisbursed' => $commitment - $disbursed, 'years' => $years];
    }
}

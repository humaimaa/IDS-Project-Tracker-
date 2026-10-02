<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTrackerRecordRequest;
use App\Models\TrackerRecord;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $demo = config('dashboard');
        $rows = collect($demo['projects'])->map(function (array $data): array {
            return ['project' => new TrackerRecord(['module' => 'projects', 'data' => $data]), 'health' => $data['health'], 'reasons' => $data['reasons']];
        })->filter(function (array $row) use ($request): bool {
            foreach (['stage' => 'stage', 'partner' => 'partners', 'sector' => 'sectors', 'district' => 'districts'] as $filter => $field) {
                if ($request->filled($filter) && ! in_array($request->string($filter)->value(), (array) $row['project']->data[$field])) {
                    return false;
                }
            }

            return ! $request->filled('search') || str_contains(mb_strtolower($row['project']->data['name'].' '.$row['project']->data['reference']), mb_strtolower($request->string('search')->value()));
        });
        $stageCounts = collect(['Concept', 'PC-I Development', 'Implementation'])->mapWithKeys(fn (string $stage): array => [$stage => $rows->filter(fn (array $row): bool => $row['project']->data['stage'] === $stage)->count()]);
        $healthCounts = collect(['Delayed', 'On track', 'On hold'])->mapWithKeys(fn (string $health): array => [$health => $rows->where('health', $health)->count()]);
        $distributions = [];
        foreach (['partners' => 'Development partners', 'sectors' => 'Sectors'] as $field => $label) {
            $distributions[$label] = $rows->flatMap(fn (array $row): array => $row['project']->data[$field])->countBy()->sortDesc();
        }
        $selectedHealth = $request->string('health')->value();
        $directory = $selectedHealth === '' ? $rows : $rows->where('health', $selectedHealth);
        $meetings = collect($demo['meetings'])->map(fn (array $data): TrackerRecord => new TrackerRecord(['module' => 'meetings', 'data' => $data]));
        $fieldOptions = ['stage' => $stageCounts->keys()->all(), 'partners' => ['ADB', 'World Bank', 'Other'], 'sectors' => ['Education', 'Health', 'Transport', 'Water'], 'districts' => config('tracker.projects.fields.8.4')];

        return view('dashboard', compact('rows', 'directory', 'stageCounts', 'healthCounts', 'distributions', 'meetings', 'selectedHealth', 'fieldOptions'))->with('demo', $demo);
    }

    public function show(string $reference): View
    {
        $data = collect(config('dashboard.projects'))->firstWhere('reference', $reference);
        abort_unless($data, 404);

        return view('projects.show', [
            'module' => 'projects',
            'definition' => config('tracker.projects'),
            'record' => new TrackerRecord(['module' => 'projects', 'data' => $data]),
            'dashboardDemo' => true,
            'projectActivities' => collect(),
        ]);
    }

    public function live(Request $request): View
    {
        $today = now('Asia/Karachi')->toDateString();
        $projects = TrackerRecord::where('module', 'projects')->latest('id')->get();
        $work = TrackerRecord::whereIn('module', ['activities', 'subactivities'])->get()->groupBy('data.project_id');
        $projects = $projects->filter(function (TrackerRecord $project) use ($request): bool {
            foreach (['stage' => 'stage', 'partner' => 'partners', 'sector' => 'sectors', 'district' => 'districts'] as $filter => $field) {
                if ($request->filled($filter) && ! in_array($request->string($filter)->value(), (array) ($project->data[$field] ?? []))) {
                    return false;
                }
            }

            return ! $request->filled('search') || str_contains(mb_strtolower(($project->data['name'] ?? '').' '.($project->data['reference'] ?? '')), mb_strtolower($request->string('search')->value()));
        });
        $rows = $projects->map(function (TrackerRecord $project) use ($work, $today): array {
            $items = $work->get((string) $project->id, collect());
            $pending = $items->filter(fn (TrackerRecord $item): bool => ! in_array($item->data['status'] ?? '', ['Completed', 'Not Applicable']) && empty($item->data['actual_completion']));
            $overdue = $pending->filter(fn (TrackerRecord $item): bool => ! empty($item->data['due']) && $item->data['due'] < $today);
            $reasons = $overdue->map(fn (TrackerRecord $item): string => ($item->module === 'activities' ? 'Activity' : 'Sub-activity').': '.($item->data['name'] ?? 'Unnamed').' ('.($item->data['stage'] ?? $project->data['stage'] ?? 'Stage not set').')')->values()->all();
            $projectOverdue = ! empty($project->data['completion']) && $project->data['completion'] < $today && empty($project->data['actual_completion']);
            if ($projectOverdue) {
                array_unshift($reasons, 'Project completion overdue');
            }
            $status = strtolower($project->data['status'] ?? 'active');
            $health = match (true) {
                $status === 'completed', ! empty($project->data['actual_completion']) => 'Completed',
                in_array($status, ['canceled', 'cancelled']) => 'Canceled',
                $status === 'on hold' => 'On hold',
                $overdue->isNotEmpty() || $projectOverdue => 'Delayed',
                $pending->contains(fn (TrackerRecord $item): bool => ! empty($item->data['due'])) || ! empty($project->data['completion']) => 'On track',
                default => 'Schedule not set',
            };

            return ['project' => $project, 'health' => $health, 'reasons' => in_array($health, ['Completed', 'Canceled']) ? [] : $reasons];
        });
        $stages = config('tracker.projects.fields.2.4');
        $stageCounts = collect($stages)->mapWithKeys(fn (string $stage): array => [$stage => $projects->where('data.stage', $stage)->count()]);
        $healthCounts = collect(['Delayed', 'On track', 'On hold'])->mapWithKeys(fn (string $health): array => [$health => $rows->where('health', $health)->count()]);
        $distributions = [];
        foreach (['partners' => 'Development partners', 'sectors' => 'Sectors'] as $field => $label) {
            $distributions[$label] = $projects->flatMap(fn (TrackerRecord $project): array => array_unique($project->data[$field] ?? []))->countBy()->sortDesc();
        }
        $selectedHealth = $request->string('health')->value();
        $directory = $selectedHealth === '' ? $rows : $rows->where('health', $selectedHealth);
        $meetings = TrackerRecord::where('module', 'meetings')->where('data->status', 'Scheduled')->where('data->date', '>=', now('Asia/Karachi')->format('Y-m-d\TH:i'))->orderBy('data->date')->limit(3)->get();

        return view('dashboard', compact('rows', 'directory', 'stageCounts', 'healthCounts', 'distributions', 'meetings', 'selectedHealth'))->with('fieldOptions', SaveTrackerRecordRequest::fieldOptions('projects'));
    }
}

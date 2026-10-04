<?php

namespace App\Http\Controllers;

use App\DashboardUpdates;
use App\Models\TrackerRecord;
use App\ProjectPortfolio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ProjectOverviewController extends Controller
{
    public function __construct(private ProjectPortfolio $portfolio) {}

    public function dashboard(Request $request, DashboardUpdates $updates): View
    {
        $rows = $this->portfolio->rows();
        $cards = [
            ['label' => 'Total projects', 'count' => $rows->count(), 'filter' => [], 'tone' => 'teal'],
            ['label' => 'Stage 1 · Pipeline', 'count' => $rows->where('phase', 'Pipeline')->count(), 'filter' => ['phase' => 'Pipeline'], 'tone' => 'blue'],
            ['label' => 'Stage 2 · Ongoing', 'count' => $rows->where('phase', 'Ongoing')->count(), 'filter' => ['phase' => 'Ongoing'], 'tone' => 'teal'],
            ['label' => 'Off-track projects', 'count' => $rows->where('has_issues', true)->count(), 'filter' => ['health' => 'Off track'], 'tone' => 'red'],
            ['label' => 'On-track projects', 'count' => $rows->where('health', 'On track')->count(), 'filter' => ['health' => 'On track'], 'tone' => 'green'],
        ];
        $charts = [];
        foreach (['partner' => ['partners', 'Development partner'], 'sector' => ['sectors', 'Sector'], 'district' => ['districts', 'District']] as $key => [$field, $title]) {
            $charts[$key] = ['title' => $title.'-wise projects', 'counts' => $rows->flatMap(fn (array $row): array => array_values(array_unique((array) ($row['project']->data[$field] ?? []))))->countBy()->sortDesc()];
        }

        $flagshipProjects = $rows->filter(fn (array $row): bool => filter_var($row['project']->data['is_flagship'] ?? false, FILTER_VALIDATE_BOOLEAN))->values();

        return view('dashboard', array_merge(compact('rows', 'cards', 'charts', 'flagshipProjects'), $updates->snapshot($request)));
    }

    public function updates(Request $request, DashboardUpdates $updates): JsonResponse
    {
        $data = $updates->snapshot($request);
        $issueProjects = $this->portfolio->rows()->where('has_issues', true)->values();

        return response()->json([
            'comments' => view('partials.dashboard-comments', $data)->render(),
            'meetings' => view('partials.dashboard-meetings', $data)->render(),
            'upcoming' => view('partials.dashboard-upcoming', $data)->render(),
            'issues' => $issueProjects->count(),
            'issueProjects' => view('partials.issue-projects', compact('issueProjects'))->render(),
        ])->header('Cache-Control', 'no-store');
    }

    public function readComments(Request $request, DashboardUpdates $updates): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array|max:200', 'ids.*' => 'required|integer|min:1']);
        $updates->markCommentsRead($request, $data['ids']);

        return response()->json(['saved' => true]);
    }

    public function index(Request $request): View
    {
        $request->validate(['search' => 'nullable|string|max:200', 'partner' => 'nullable|string|max:200', 'sector' => 'nullable|string|max:200', 'district' => 'nullable|string|max:200', 'phase' => 'nullable|in:Pipeline,Ongoing', 'health' => 'nullable|in:Off track,On track', 'page' => 'nullable|integer|min:1']);
        $all = $this->portfolio->rows();
        $rows = $all->filter(function (array $row) use ($request): bool {
            foreach (['partner' => 'partners', 'sector' => 'sectors', 'district' => 'districts'] as $key => $field) {
                if ($request->filled($key) && ! in_array($request->input($key), (array) ($row['project']->data[$field] ?? []))) {
                    return false;
                }
            }
            if ($request->filled('phase') && $row['phase'] !== $request->input('phase')) {
                return false;
            }
            if ($request->filled('health') && ($request->input('health') === 'Off track' ? ! $row['has_issues'] : $row['health'] !== $request->input('health'))) {
                return false;
            }
            $search = mb_strtolower($request->string('search')->trim()->value());

            return $search === '' || str_contains(mb_strtolower(($row['project']->data['name'] ?? '').' '.($row['project']->data['reference'] ?? '')), $search);
        })->values();
        $records = new LengthAwarePaginator($rows->forPage($request->integer('page', 1), 15)->values(), $rows->count(), 15, $request->integer('page', 1), ['path' => route('projects.index'), 'query' => $request->query()]);

        return view('projects.portfolio-index', compact('records', 'all'));
    }

    public function show(Request $request, TrackerRecord $record): View
    {
        abort_unless($record->module === 'projects', 404);

        return $this->page($request, $record);
    }

    public function demo(Request $request, string $reference): View
    {
        $data = collect(config('dashboard.projects'))->firstWhere('reference', $reference);
        abort_unless($data, 404);

        return $this->page($request, new TrackerRecord(['module' => 'projects', 'data' => $data]));
    }

    private function page(Request $request, TrackerRecord $record): View
    {
        $request->validate(['tab' => 'nullable|in:general,financial,physical,pipeline', 'year' => 'nullable|in:2024-25,2025-26,2026-27', 'quarter' => 'nullable|in:all,1,2,3,4']);
        $work = $record->exists ? TrackerRecord::whereIn('module', ['activities', 'subactivities'])->where('data->project_id', (string) $record->id)->get() : collect();
        $row = $this->portfolio->row($record, $work, $record->exists ? TrackerRecord::where('module', 'issues')->get() : collect(), ! $record->exists);
        $projectIssues = TrackerRecord::where('module', 'issues')->get()->filter(function (TrackerRecord $issue) use ($record): bool {
            return isset($issue->data['project_id'])
                ? $record->exists && (string) $issue->data['project_id'] === (string) $record->id
                : ($issue->data['project'] ?? '') === ($record->data['name'] ?? '');
        })->map(fn (TrackerRecord $issue): array => array_merge($issue->data, ['url' => route('issues.show', $issue), 'demo' => false]))->values();
        if ($row['demo']) {
            $projectIssues = collect($record->data['issues'] ?? []);
        }
        $openIssues = $projectIssues->reject(fn (array $issue): bool => in_array(strtolower($issue['status'] ?? 'open'), ['resolved', 'closed', 'cancelled', 'canceled']))->values();
        $tab = $request->input('tab') ?: 'general';
        if ($row['phase'] === 'Pipeline' && $tab === 'financial') {
            $tab = 'general';
        }
        $componentTab = $row['phase'] === 'Pipeline' ? 'pipeline' : 'physical';
        if (in_array($tab, ['pipeline', 'physical'])) {
            $tab = $componentTab;
        }
        $components = $this->portfolio->components($record, $componentTab);
        $finance = $row['phase'] === 'Ongoing' ? $this->portfolio->finances($record) : [];
        $year = $request->input('year') ?: '2026-27';
        $quarter = $request->input('quarter') ?: 'all';
        $annual = $finance['years'][$year] ?? ['quarters' => []];
        $quarters = $quarter === 'all' ? $annual['quarters'] : array_intersect_key($annual['quarters'], [(int) $quarter => true]);

        return view('projects.overview', compact('record', 'row', 'tab', 'components', 'finance', 'year', 'quarter', 'annual', 'quarters', 'work', 'projectIssues', 'openIssues'));
    }
}

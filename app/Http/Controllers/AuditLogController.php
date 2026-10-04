<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['search' => 'nullable|string|max:200', 'action' => 'nullable|in:created,updated,deleted', 'module' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $examples = [
            ['updated', 'projects', 'District Water Supply Improvement', 'Aina Khan', 'status', 'On track', 'Delayed'],
            ['created', 'accounts', 'Sara Ahmed', 'Admin User', 'account', null, 'Sara Ahmed - Section Officer'],
            ['updated', 'activities', 'Component 2 - Site preparation', 'Muhammad Ali', 'progress', '35%', '60%'],
            ['created', 'meetings', 'Development Partner Review', 'Aina Khan', 'location', null, 'Planning & Development Conference Room'],
            ['created', 'project_comments', 'District Water Supply Improvement', 'Sara Ahmed', 'comment', null, 'Updated implementation schedule shared for review.'],
            ['updated', 'projects', 'Rural Health Centres Upgrade', 'Muhammad Ali', 'cost', 'PKR 120 million', 'PKR 135 million'],
            ['deleted', 'issues', 'Missing feasibility document', 'Aina Khan', 'issue', 'Duplicate issue record', null],
            ['created', 'partners', 'Development Partner A', 'Admin User', 'name', null, 'Development Partner A'],
            ['updated', 'roles', 'Project Officer', 'Admin User', 'permissions', 'View projects', 'View and update projects'],
            ['deleted', 'meetings', 'Draft Coordination Meeting', 'Sara Ahmed', 'status', 'Draft', null],
            ['created', 'projects', 'School Facilities Improvement', 'Aina Khan', 'stage', null, 'Pipeline'],
            ['updated', 'sectors', 'Education', 'Admin User', 'name', 'School education', 'Education'],
        ];
        $all = collect($examples)->map(fn (array $entry, int $index): object => (object) [
            'action' => $entry[0], 'module' => $entry[1], 'subject_name' => $entry[2], 'actor_name' => $entry[3],
            'subject_id' => 100 + $index, 'actor_id' => null,
            'created_at' => now('Asia/Karachi')->startOfDay()->addHours(10)->subMinutes($index * 45),
            'changes' => [$entry[4] => ['before' => $entry[5], 'after' => $entry[6]]],
        ]);
        $rows = $all->filter(function (object $log) use ($request): bool {
            foreach (['action', 'module'] as $field) {
                if ($request->filled($field) && $log->{$field} !== $request->input($field)) {
                    return false;
                }
            }

            return ! $request->filled('search') || str_contains(mb_strtolower($log->subject_name.' '.$log->actor_name), mb_strtolower($request->input('search')));
        })->values();
        $page = $request->integer('page', 1);
        $logs = new LengthAwarePaginator($rows->forPage($page, 10)->values(), $rows->count(), 10, $page, ['path' => route('logs.index'), 'query' => $request->query()]);

        return view('audit-logs', ['logs' => $logs, 'modules' => $all->pluck('module')->unique()->sort()]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTrackerRecordRequest;
use App\Models\TrackerRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TrackerController extends Controller
{
    private function module(Request $request): string
    {
        return explode('.', $request->route()->getName())[0];
    }

    private function page(Request $request, string $page, ?TrackerRecord $record = null): View
    {
        $module = $this->module($request);
        if ($record) {
            abort_unless($record->module === $module, 404);
        }

        $viewModule = $module === 'subactivities' ? 'activities' : $module;

        return view("{$viewModule}.{$page}", [
            'module' => $module,
            'definition' => config("tracker.{$module}"),
            'record' => $record,
            'assignedRole' => $module === 'users' && $record ? TrackerRecord::where('module', 'roles')->where('data->name', $record->data['role'] ?? '')->first() : null,
            'projectOptions' => in_array($module, ['activities', 'subactivities']) ? TrackerRecord::where('module', 'projects')->get() : collect(),
            'parentOptions' => $module === 'subactivities' ? TrackerRecord::where('module', 'activities')->get() : collect(),
            'projectActivities' => $module === 'projects' && $record ? TrackerRecord::where('module', 'activities')->where('data->project_id', (string) $record->id)->get() : collect(),
            'fieldOptions' => SaveTrackerRecordRequest::fieldOptions($module, $record),
        ]);
    }

    public function index(Request $request): View
    {
        $query = TrackerRecord::query()->where('module', $this->module($request));
        $search = $request->string('search')->trim()->value();
        if ($search !== '') {
            $query->where('data', 'like', '%'.$search.'%');
        }

        if (in_array($this->module($request), ['activities', 'subactivities'])) {
            foreach (['project_id', 'stage', 'type'] as $filter) {
                if ($request->filled($filter)) {
                    $query->where('data->'.$filter, $request->input($filter));
                }
            }
        }

        return $this->page($request, 'index')->with('records', $query->latest('id')->paginate(15)->withQueryString());
    }

    public function create(Request $request): View
    {
        return $this->page($request, 'create');
    }

    public function sampleShow(Request $request, string $sample): View
    {
        return $this->page($request, 'show')->with('sample', (int) $sample);
    }

    public function sampleEdit(Request $request, string $sample): View
    {
        return $this->page($request, 'edit')->with('sample', (int) $sample);
    }

    public function store(SaveTrackerRecordRequest $request): RedirectResponse
    {
        $module = $this->module($request);
        $record = DB::transaction(function () use ($request, $module): TrackerRecord {
            $record = TrackerRecord::create(['module' => $module, 'data' => $this->recordData($request)]);
            if ($module === 'projects') {
                $record->createDefaultActivities();
            }

            return $record;
        });

        return to_route("{$module}.show", $record)->with('success', 'Record created.');
    }

    public function show(Request $request, TrackerRecord $record): View
    {
        return $this->page($request, 'show', $record);
    }

    public function edit(Request $request, TrackerRecord $record): View
    {
        return $this->page($request, 'edit', $record);
    }

    public function update(SaveTrackerRecordRequest $request, TrackerRecord $record): RedirectResponse
    {
        $module = $this->module($request);
        abort_unless($record->module === $module, 404);
        DB::transaction(function () use ($request, $record, $module): void {
            $oldName = $record->data['name'] ?? '';
            $record->update(['data' => $this->recordData($request, $record)]);
            if ($module === 'projects' && $oldName !== $record->data['name']) {
                foreach (TrackerRecord::whereIn('module', ['activities', 'subactivities'])->where('data->project_id', (string) $record->id)->get() as $item) {
                    $item->update(['data' => array_replace($item->data, ['project' => $record->data['name']])]);
                }
            }
            if ($module === 'activities') {
                foreach (TrackerRecord::where('module', 'subactivities')->where('data->parent_id', (string) $record->id)->get() as $child) {
                    $child->update(['data' => array_replace($child->data, ['parent_activity' => $record->data['name']])]);
                }
            }
            if ($module === 'roles' && $oldName !== $record->data['name']) {
                foreach (TrackerRecord::where('module', 'users')->where('data->role', $oldName)->get() as $user) {
                    $user->update(['data' => array_replace($user->data, ['role' => $record->data['name']])]);
                }
            }
        });

        return to_route("{$module}.show", $record)->with('success', 'Record updated.');
    }

    private function recordData(SaveTrackerRecordRequest $request, ?TrackerRecord $record = null): array
    {
        $data = $request->safe()->except('documents');
        $module = $this->module($request);
        if (in_array($module, ['activities', 'subactivities'])) {
            $project = TrackerRecord::where('module', 'projects')->findOrFail($data['project_id']);
            $data['project'] = $project->data['name'];
            $data['project_id'] = (string) $project->id;
            if ($module === 'subactivities') {
                $parent = TrackerRecord::where('module', 'activities')->findOrFail($data['parent_id']);
                $data['parent_id'] = (string) $parent->id;
                $data['parent_activity'] = $parent->data['name'];
            }
            if ($data['type'] === 'Predefined') {
                $templates = config('tracker.projects.activity_templates');
                $data['name'] = $module === 'activities'
                    ? $templates[$data['template_id']]['name']
                    : $templates[$parent->data['template_id']]['subactivities'][$data['template_id']];
            } else {
                unset($data['template_id']);
            }
        }
        $attachments = $record?->data['attachments'] ?? [];
        foreach ($request->file('documents', []) as $file) {
            $attachments[] = [
                'title' => $file->getClientOriginalName(),
                'path' => $file->store('tracker-documents', 'local'),
                'uploaded_at' => now()->toDateTimeString(),
            ];
        }
        if ($attachments !== []) {
            $data['attachments'] = $attachments;
        }

        return $data;
    }

    public function delete(Request $request, TrackerRecord $record): View
    {
        abort_if($this->module($request) === 'projects', 405);

        return $this->page($request, 'delete', $record);
    }

    public function destroy(Request $request, TrackerRecord $record): RedirectResponse
    {
        $module = $this->module($request);
        abort_unless($record->module === $module, 404);
        abort_if($module === 'projects', 405);
        if ($module === 'roles' && TrackerRecord::where('module', 'users')->where('data->role', $record->data['name'])->exists()) {
            return back()->withErrors(['role' => 'Reassign users before deleting this role.']);
        }
        if ($module === 'activities' && TrackerRecord::where('module', 'subactivities')->where('data->parent_id', (string) $record->id)->exists()) {
            return back()->withErrors(['parent_id' => 'Reassign or delete the sub-activities before deleting this activity.']);
        }
        $record->delete();

        return to_route("{$module}.index")->with('success', 'Record deleted.');
    }
}

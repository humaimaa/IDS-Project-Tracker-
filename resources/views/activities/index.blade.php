@extends('layouts.app')

@section('content')
@php
    $samples = $module === 'subactivities' ? [] : [
        1 => ['name' => 'Prepare PC-I', 'project' => 'Secondary Schools Upgradation', 'stage' => 'PC-I Development', 'description' => 'Prepare the project proposal for review.', 'officer' => 'Ayesha Khan', 'start' => '2026-09-01', 'due' => '2026-10-20', 'status' => 'In Progress', 'remarks' => 'District information is being collected.'],
        2 => ['name' => 'Inspect existing buildings', 'project' => 'Rural Health Centres (Phase I)', 'stage' => 'Implementation', 'parent_activity' => 'Upgrade health centres', 'description' => 'Inspect facilities before construction.', 'officer' => 'District Engineer', 'start' => '2026-09-20', 'due' => '2026-10-15', 'status' => 'In Progress'],
        3 => ['name' => 'Review concept note', 'project' => 'Urban Flood Protection Scheme', 'stage' => 'Concept', 'officer' => 'Umar Shah', 'due' => '2026-11-01', 'status' => 'Not Started'],
    ];
@endphp
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">{{ $definition['title'] }}</h1>
    <a class="rounded-lg bg-teal px-4 py-3 text-sm font-semibold text-white" href="{{ route($module.'.create') }}">Add {{ strtolower($definition['singular']) }}</a>
</div>
<div class="panel overflow-hidden">
    <form class="flex flex-wrap gap-3 border-b border-slate-100 p-4" method="get" action="{{ route($module.'.index') }}">
        <input class="field max-w-sm" name="search" value="{{ request('search') }}" placeholder="Search this list…" aria-label="Search records">
        <select class="field max-w-xs" name="project_id" aria-label="Filter by project"><option value="">All projects</option>@foreach($projectOptions as $project)<option value="{{ $project->id }}" @selected((string) request('project_id') === (string) $project->id)>{{ $project->data['name'] }}</option>@endforeach</select>
        <select class="field max-w-xs" name="type" aria-label="Filter by type"><option value="">All types</option>@foreach(['Predefined', 'Custom'] as $type)<option @selected(request('type') === $type)>{{ $type }}</option>@endforeach</select>
        <select class="field max-w-xs" name="stage" aria-label="Filter by stage"><option value="">All stages</option>@foreach(['Concept', 'PC-I Development', 'Implementation'] as $stage)<option @selected(request('stage') === $stage)>{{ $stage }}</option>@endforeach</select>
        <button class="rounded-lg bg-teal px-4 text-sm text-white">Search</button>
        @if(request()->hasAny(['search', 'project_id', 'type', 'stage']))<a class="p-2 text-teal" href="{{ route($module.'.index') }}">Clear</a>@endif
    </form>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-slate-50 text-xs text-slate-500"><tr>@foreach($definition['labels'] as $label)<th class="px-4 py-3">{{ $label }}</th>@endforeach<th class="px-4 py-3">Actions</th></tr></thead>
            <tbody>
                @foreach(request()->hasAny(['project_id', 'type', 'stage']) ? [] : $samples as $sampleId => $sampleData)
                    @continue(request('search') && !str_contains(strtolower(implode(' ', array_filter($sampleData, 'is_string'))), strtolower(request('search'))))
                    <tr class="table-row">
                        @foreach($definition['columns'] as $column)
                            <td class="table-cell">{{ is_array($sampleData[$column] ?? null) ? implode(', ', $sampleData[$column]) : ($sampleData[$column] ?? '—') }}@if($loop->first)<span class="mt-1 block text-xs text-slate-400">Example</span>@endif</td>
                        @endforeach
                        <td class="table-cell"><div class="flex gap-3"><a class="text-teal" href="{{ route($module.'.sample-show', $sampleId) }}">View</a><a class="text-teal" href="{{ route($module.'.sample-edit', $sampleId) }}">Edit</a></div></td>
                    </tr>
                @endforeach
                @forelse($records as $item)
                    <tr class="table-row">
                        @foreach($definition['columns'] as $column)<td class="table-cell">{{ is_array($item->data[$column] ?? null) ? implode(', ', $item->data[$column]) : ($item->data[$column] ?? '—') }}</td>@endforeach
                        <td class="table-cell"><div class="flex items-center gap-3">
                            <a class="text-teal" href="{{ route($module.'.show', $item) }}">View</a>
                            <a class="text-teal" href="{{ route($module.'.edit', $item) }}">Edit</a>
                            <a class="text-rose-600" href="{{ route($module.'.delete', $item) }}">Delete</a>
                        </div></td>
                    </tr>
                @empty
                    <tr><td class="p-10 text-center text-slate-500" colspan="{{ count($definition['columns']) + 1 }}">No saved records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $records->links() }}</div>
</div>
@endsection

@extends('layouts.app')

@section('content')
@php
    $samples = [
        1 => ['name' => 'Cost estimates pending', 'project' => 'Secondary Schools Upgradation', 'stage' => 'PC-I Development', 'activity' => 'Prepare PC-I', 'subactivity' => 'Prepare cost estimates', 'description' => 'Updated engineering estimates are required.', 'reported_at' => '2026-09-25', 'reported_by' => 'Ayesha Khan', 'priority' => 'High', 'officer' => 'Section Officer', 'action' => 'Obtain revised estimates from the engineering team.', 'due' => '2026-10-06', 'status' => 'Open', 'follow_up' => 'Reminder sent to the engineering team.'],
        2 => ['name' => 'Site access awaiting clearance', 'project' => 'Rural Health Centres (Phase I)', 'stage' => 'Implementation', 'activity' => 'Upgrade health centres', 'description' => 'Access clearance is required for two sites.', 'reported_at' => '2026-09-28', 'reported_by' => 'Dr. Sana Ali', 'priority' => 'Medium', 'officer' => 'Project Director', 'action' => 'Coordinate with district administration.', 'due' => '2026-10-12', 'status' => 'Being Addressed'],
        3 => ['name' => 'Survey data received', 'project' => 'Urban Flood Protection Scheme', 'stage' => 'Concept', 'description' => 'Missing survey information has been provided.', 'reported_at' => '2026-09-10', 'reported_by' => 'Umar Shah', 'priority' => 'Low', 'officer' => 'District Engineer', 'status' => 'Resolved', 'resolution' => 'Survey report received and reviewed on 28 September 2026.'],
    ];
    if (config('dashboard.presentation')) { $samples = config('dashboard.issue_samples'); }
@endphp
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">{{ $definition['title'] }}</h1>
    <a class="rounded-lg bg-teal px-4 py-3 text-sm font-semibold text-white" href="{{ route($module.'.create') }}">Add {{ strtolower($definition['singular']) }}</a>
</div>
<div class="panel overflow-hidden">
    <form class="flex gap-3 border-b border-slate-100 p-4" method="get" action="{{ route($module.'.index') }}">
        <input class="field max-w-sm" name="search" value="{{ request('search') }}" placeholder="Search this list…" aria-label="Search records">
        <button class="rounded-lg bg-teal px-4 text-sm text-white">Search</button>
        @if(request('search'))<a class="p-2 text-teal" href="{{ route($module.'.index') }}">Clear</a>@endif
    </form>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-slate-50 text-xs text-slate-500"><tr>@foreach($definition['labels'] as $label)<th class="px-4 py-3">{{ $label }}</th>@endforeach<th class="px-4 py-3">Actions</th></tr></thead>
            <tbody>
                @foreach($samples as $sampleId => $sampleData)
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
                    @if(request('search') && !collect($samples)->contains(fn ($row) => str_contains(strtolower(implode(' ', array_filter($row, 'is_string'))), strtolower(request('search')))))
                    <tr><td class="p-10 text-center text-slate-500" colspan="{{ count($definition['columns']) + 1 }}">No records found.</td></tr>
                    @endif
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $records->links() }}</div>
</div>
@endsection

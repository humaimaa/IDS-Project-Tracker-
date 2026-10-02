@extends('layouts.app')

@section('content')
@php
    $samples = [
        1 => ['name' => 'Administrator', 'description' => 'Maintains tracker records and user access.', 'permissions' => ['View projects', 'Edit projects', 'Update activities', 'Manage issues', 'Resolve issues', 'Record meetings', 'Upload documents', 'Download reports', 'Archive records', 'Manage users', 'Manage reference lists']],
        2 => ['name' => 'Section Officer', 'description' => 'Coordinates projects and follow-up work.', 'permissions' => ['View projects', 'Edit projects', 'Update activities', 'Manage issues', 'Record meetings', 'Upload documents']],
        3 => ['name' => 'Donor Viewer', 'description' => 'Views assigned projects and permitted reports.', 'permissions' => ['View projects', 'Download reports']],
    ];
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

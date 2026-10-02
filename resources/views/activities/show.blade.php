@extends('layouts.app')

@section('content')
@if(isset($sample))
    @php
    $samples = [
        1 => ['name' => 'Prepare PC-I', 'project' => 'Secondary Schools Upgradation', 'stage' => 'PC-I Development', 'description' => 'Prepare the project proposal for review.', 'officer' => 'Ayesha Khan', 'start' => '2026-09-01', 'due' => '2026-10-20', 'status' => 'In Progress', 'remarks' => 'District information is being collected.'],
        2 => ['name' => 'Inspect existing buildings', 'project' => 'Rural Health Centres (Phase I)', 'stage' => 'Implementation', 'parent_activity' => 'Upgrade health centres', 'description' => 'Inspect facilities before construction.', 'officer' => 'District Engineer', 'start' => '2026-09-20', 'due' => '2026-10-15', 'status' => 'In Progress'],
        3 => ['name' => 'Review concept note', 'project' => 'Urban Flood Protection Scheme', 'stage' => 'Concept', 'officer' => 'Umar Shah', 'due' => '2026-11-01', 'status' => 'Not Started'],
    ];
@endphp
    @php
        abort_unless(isset($samples[$sample]), 404);
        $record = new \App\Models\TrackerRecord(['module' => 'activities', 'data' => $samples[$sample]]);
    @endphp
    <p class="mb-4 text-sm text-slate-500">Example record.</p>
@endif
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">{{ $record->data['name'] }}</h1>
    <div class="flex items-center gap-4"><a class="text-sm text-teal" href="{{ route($module.'.index') }}">Back to list</a><a class="rounded-lg bg-teal px-4 py-2 text-sm text-white" href="{{ isset($sample) ? route($module.'.sample-edit', $sample) : route($module.'.edit', $record) }}">Edit {{ strtolower($definition['singular']) }}</a></div>
</div>
<dl class="panel grid gap-6 p-6 sm:grid-cols-2">
    @foreach($definition['fields'] as $field)
        @continue($field[0] === 'template_id')
        @php($key = ['project_id' => 'project', 'parent_id' => 'parent_activity'][$field[0]] ?? $field[0])
        <div><dt class="text-xs font-semibold text-slate-500">{{ $field[1] }}</dt><dd class="mt-2 whitespace-pre-wrap break-words text-sm">{{ $record->data[$key] ?? '—' }}</dd></div>
    @endforeach
</dl>
@if($record->exists && $module === 'activities')
    <a class="mt-5 inline-block text-sm font-semibold text-teal" href="{{ route('subactivities.create', ['project_id' => $record->data['project_id'] ?? '', 'stage' => $record->data['stage'] ?? '', 'parent_id' => $record->id]) }}">Add sub-activity</a>
@endif
@include('partials.documents-show')
@endsection

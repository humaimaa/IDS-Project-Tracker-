@extends('layouts.app')

@section('content')
@if(isset($sample))
    @php
    $samples = [
        1 => ['name' => 'Cost estimates pending', 'project' => 'Secondary Schools Upgradation', 'stage' => 'PC-I Development', 'activity' => 'Prepare PC-I', 'subactivity' => 'Prepare cost estimates', 'description' => 'Updated engineering estimates are required.', 'reported_at' => '2026-09-25', 'reported_by' => 'Ayesha Khan', 'priority' => 'High', 'officer' => 'Section Officer', 'action' => 'Obtain revised estimates from the engineering team.', 'due' => '2026-10-06', 'status' => 'Open', 'follow_up' => 'Reminder sent to the engineering team.'],
        2 => ['name' => 'Site access awaiting clearance', 'project' => 'Rural Health Centres (Phase I)', 'stage' => 'Implementation', 'activity' => 'Upgrade health centres', 'description' => 'Access clearance is required for two sites.', 'reported_at' => '2026-09-28', 'reported_by' => 'Dr. Sana Ali', 'priority' => 'Medium', 'officer' => 'Project Director', 'action' => 'Coordinate with district administration.', 'due' => '2026-10-12', 'status' => 'Being Addressed'],
        3 => ['name' => 'Survey data received', 'project' => 'Urban Flood Protection Scheme', 'stage' => 'Concept', 'description' => 'Missing survey information has been provided.', 'reported_at' => '2026-09-10', 'reported_by' => 'Umar Shah', 'priority' => 'Low', 'officer' => 'District Engineer', 'status' => 'Resolved', 'resolution' => 'Survey report received and reviewed on 28 September 2026.'],
    ];
    if (config('dashboard.presentation')) { $samples = config('dashboard.issue_samples'); }
@endphp
    @php
        abort_unless(isset($samples[$sample]), 404);
        $record = new \App\Models\TrackerRecord(['module' => 'issues', 'data' => $samples[$sample]]);
    @endphp
    <p class="mb-4 text-sm text-slate-500">Example record.</p>
@endif
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">{{ $record->data['name'] }}</h1>
    <div class="flex items-center gap-4"><a class="text-sm text-teal" href="{{ route($module.'.index') }}">Back to list</a><a class="rounded-lg bg-teal px-4 py-2 text-sm text-white" href="{{ isset($sample) ? route($module.'.sample-edit', $sample) : route($module.'.edit', $record) }}">Edit {{ strtolower($definition['singular']) }}</a></div>
</div>
<dl class="panel grid gap-6 p-6 sm:grid-cols-2">
    @foreach($definition['fields'] as $field)<div><dt class="text-xs font-semibold text-slate-500">{{ $field[1] }}</dt><dd class="mt-2 whitespace-pre-wrap break-words text-sm">{{ is_array($record->data[$field[0]] ?? null) ? implode(', ', $record->data[$field[0]]) : ($record->data[$field[0]] ?? '—') }}</dd></div>@endforeach
</dl>
@include('partials.documents-show')
@endsection

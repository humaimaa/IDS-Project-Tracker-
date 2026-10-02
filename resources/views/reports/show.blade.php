@extends('layouts.app')

@section('content')
@if(isset($sample))
    @php
    $samples = [
        1 => ['name' => 'Quarterly project overview', 'type' => 'Projects by stage', 'projects' => ['Rural Health Centres (Phase I)', 'Secondary Schools Upgradation', 'Urban Flood Protection Scheme'], 'period_start' => '2026-07-01', 'period_end' => '2026-09-30', 'status' => 'PDF'],
        2 => ['name' => 'Open issues and owners', 'type' => 'Unresolved issues', 'projects' => ['Rural Health Centres (Phase I)', 'Secondary Schools Upgradation'], 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'status' => 'Excel'],
        3 => ['name' => 'Meeting follow-up summary', 'type' => 'Meetings and pending actions', 'projects' => ['Rural Health Centres (Phase I)', 'Secondary Schools Upgradation', 'Urban Flood Protection Scheme'], 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'status' => 'PDF'],
    ];
@endphp
    @php
        abort_unless(isset($samples[$sample]), 404);
        $record = new \App\Models\TrackerRecord(['module' => 'reports', 'data' => $samples[$sample]]);
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
@endsection

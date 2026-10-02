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
    <p class="mb-4 text-sm text-slate-500">Example record. Editing saves a new record; the example stays available.</p>
@endif
<div class="mb-5 flex items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">Edit {{ strtolower($definition['singular']) }}</h1>
    <a class="text-sm font-semibold text-teal" href="{{ route('reports.index') }}">Back to list</a>
</div>
@include('reports.partials.form')
@endsection

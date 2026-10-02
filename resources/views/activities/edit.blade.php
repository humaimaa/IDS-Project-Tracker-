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
    <p class="mb-4 text-sm text-slate-500">Example record. Editing saves a new record; the example stays available.</p>
@endif
<div class="mb-5 flex items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">Edit {{ strtolower($definition['singular']) }}</h1>
    <a class="text-sm font-semibold text-teal" href="{{ route($module.'.index') }}">Back to list</a>
</div>
@include('activities.partials.form')
@endsection

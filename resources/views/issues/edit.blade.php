@extends('layouts.app')

@section('content')
@if(isset($sample))
    @php
    $samples = [
        1 => ['name' => 'Cost estimates pending', 'project' => 'Secondary Schools Upgradation', 'stage' => 'PC-I Development', 'activity' => 'Prepare PC-I', 'subactivity' => 'Prepare cost estimates', 'description' => 'Updated engineering estimates are required.', 'reported_at' => '2026-09-25', 'reported_by' => 'Ayesha Khan', 'priority' => 'High', 'officer' => 'Section Officer', 'action' => 'Obtain revised estimates from the engineering team.', 'due' => '2026-10-06', 'status' => 'Open', 'follow_up' => 'Reminder sent to the engineering team.'],
        2 => ['name' => 'Site access awaiting clearance', 'project' => 'Rural Health Centres (Phase I)', 'stage' => 'Implementation', 'activity' => 'Upgrade health centres', 'description' => 'Access clearance is required for two sites.', 'reported_at' => '2026-09-28', 'reported_by' => 'Dr. Sana Ali', 'priority' => 'Medium', 'officer' => 'Project Director', 'action' => 'Coordinate with district administration.', 'due' => '2026-10-12', 'status' => 'Being Addressed'],
        3 => ['name' => 'Survey data received', 'project' => 'Urban Flood Protection Scheme', 'stage' => 'Concept', 'description' => 'Missing survey information has been provided.', 'reported_at' => '2026-09-10', 'reported_by' => 'Umar Shah', 'priority' => 'Low', 'officer' => 'District Engineer', 'status' => 'Resolved', 'resolution' => 'Survey report received and reviewed on 28 September 2026.'],
    ];
@endphp
    @php
        abort_unless(isset($samples[$sample]), 404);
        $record = new \App\Models\TrackerRecord(['module' => 'issues', 'data' => $samples[$sample]]);
    @endphp
    <p class="mb-4 text-sm text-slate-500">Example record. Editing saves a new record; the example stays available.</p>
@endif
<div class="mb-5 flex items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">Edit {{ strtolower($definition['singular']) }}</h1>
    <a class="text-sm font-semibold text-teal" href="{{ route('issues.index') }}">Back to list</a>
</div>
@include('issues.partials.form')
@endsection

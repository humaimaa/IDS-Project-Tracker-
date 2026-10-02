@extends('layouts.app')

@section('content')
@if(isset($sample))
    @php
    $samples = [
        1 => ['name' => 'ADB Project Review', 'projects' => ['Secondary Schools Upgradation', 'Urban Flood Protection Scheme'], 'date' => '2026-10-05T10:00', 'venue' => 'Civil Secretariat, Peshawar', 'participants' => 'ADB representatives; Education Department; Irrigation Department', 'agenda' => 'Review project preparation and pending actions.', 'status' => 'Scheduled', 'actions' => [['description' => 'Share revised cost estimates', 'officer' => 'Section Officer', 'due' => '2026-10-08', 'status' => 'Not Started', 'activity' => 'Prepare PC-I', 'issue' => 'Cost estimates pending']]],
        2 => ['name' => 'Health Facilities Coordination', 'projects' => ['Rural Health Centres (Phase I)'], 'date' => '2026-09-28T11:30', 'venue' => 'Conference Room 2', 'participants' => 'Health Department; Project Director', 'agenda' => 'Review site readiness.', 'status' => 'Held', 'minutes' => 'The team reviewed access constraints and agreed on next steps.', 'decisions' => 'Complete access clearance before mobilisation.', 'actions' => [['description' => 'Obtain site access clearance', 'officer' => 'Project Director', 'due' => '2026-10-12', 'status' => 'In Progress']]],
        3 => ['name' => 'PC-I Technical Review', 'projects' => ['Secondary Schools Upgradation'], 'date' => '2026-10-12T14:00', 'venue' => 'Planning and Development Department', 'status' => 'Scheduled', 'agenda' => 'Review the draft PC-I.'],
    ];
@endphp
    @php
        abort_unless(isset($samples[$sample]), 404);
        $record = new \App\Models\TrackerRecord(['module' => 'meetings', 'data' => $samples[$sample]]);
    @endphp
    <p class="mb-4 text-sm text-slate-500">Example record. Editing saves a new record; the example stays available.</p>
@endif
<div class="mb-5 flex items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">Edit {{ strtolower($definition['singular']) }}</h1>
    <a class="text-sm font-semibold text-teal" href="{{ route('meetings.index') }}">Back to list</a>
</div>
@include('meetings.partials.form')
@endsection

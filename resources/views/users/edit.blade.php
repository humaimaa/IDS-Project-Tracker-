@extends('layouts.app')

@section('content')
@if(isset($sample))
    @php
    $samples = [
        1 => ['name' => 'Ayesha Khan', 'email' => 'ayesha.khan@example.org', 'role' => 'Section Officer', 'status' => 'Active', 'projects' => ['Secondary Schools Upgradation']],
        2 => ['name' => 'Dr. Sana Ali', 'email' => 'sana.ali@example.org', 'role' => 'Supervisor', 'status' => 'Active', 'projects' => ['Rural Health Centres (Phase I)']],
        3 => ['name' => 'ADB Viewer', 'email' => 'adb.viewer@example.org', 'role' => 'Donor Viewer', 'status' => 'Active', 'projects' => ['Secondary Schools Upgradation', 'Urban Flood Protection Scheme']],
    ];
@endphp
    @php
        abort_unless(isset($samples[$sample]), 404);
        $record = new \App\Models\TrackerRecord(['module' => 'users', 'data' => $samples[$sample]]);
    @endphp
    <p class="mb-4 text-sm text-slate-500">Example record. Editing saves a new record; the example stays available.</p>
@endif
<div class="mb-5 flex items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">Edit {{ strtolower($definition['singular']) }}</h1>
    <a class="text-sm font-semibold text-teal" href="{{ route('users.index') }}">Back to list</a>
</div>
@include('users.partials.form')
@endsection

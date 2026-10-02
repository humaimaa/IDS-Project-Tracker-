@extends('layouts.app')

@section('content')
@if(isset($sample))
    @php
    $samples = [
        1 => ['name' => 'Rural Health Centres (Phase I)', 'reference' => 'IDS-2024-018', 'description' => 'Upgrade rural health facilities and improve access to essential services.', 'cost' => '840000000', 'currency' => 'PKR', 'agency' => 'Health Department', 'districts' => ['Kohat', 'Khyber'], 'sectors' => ['Health'], 'partners' => ['World Bank'], 'officer' => 'Dr. Sana Ali', 'stage' => 'Implementation', 'status' => 'Active', 'start' => '2025-01-01', 'completion' => '2027-06-30', 'actual_start' => '2025-02-01', 'activities' => [['name' => 'Upgrade health centres', 'stage' => 'Implementation', 'description' => 'Prepare sites and upgrade facilities.', 'officer' => 'Project Director', 'start' => '2026-09-01', 'due' => '2026-12-31', 'status' => 'In Progress', 'remarks' => 'Site preparation under way.', 'subactivities' => [['name' => 'Inspect existing buildings', 'officer' => 'District Engineer', 'due' => '2026-10-15', 'status' => 'In Progress']]]]],
        2 => ['name' => 'Secondary Schools Upgradation', 'reference' => 'IDS-2025-006', 'description' => 'Improve classrooms and facilities in secondary schools.', 'cost' => '1200000000', 'currency' => 'PKR', 'agency' => 'Education Department', 'districts' => ['Peshawar', 'Swat'], 'sectors' => ['Education'], 'partners' => ['ADB'], 'officer' => 'Ayesha Khan', 'stage' => 'PC-I Development', 'status' => 'Active', 'start' => '2026-07-01', 'completion' => '2028-06-30'],
        3 => ['name' => 'Urban Flood Protection Scheme', 'reference' => 'IDS-2026-002', 'description' => 'Develop flood protection works for vulnerable urban communities.', 'cost' => '2000000', 'currency' => 'USD', 'agency' => 'Irrigation Department', 'districts' => ['Swat'], 'sectors' => ['Water'], 'partners' => ['ADB', 'World Bank'], 'officer' => 'Umar Shah', 'stage' => 'Concept', 'status' => 'On hold', 'start' => '2027-01-01', 'completion' => '2029-12-31'],
    ];
@endphp
    @php
        abort_unless(isset($samples[$sample]), 404);
        $record = new \App\Models\TrackerRecord(['module' => 'projects', 'data' => $samples[$sample]]);
    @endphp
    <p class="mb-4 text-sm text-slate-500">Example record. Editing saves a new record; the example stays available.</p>
@endif
<div class="mb-5 flex items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">Edit {{ strtolower($definition['singular']) }}</h1>
    <a class="text-sm font-semibold text-teal" href="{{ route('projects.index') }}">Back to list</a>
</div>
@include('projects.partials.form')
@endsection

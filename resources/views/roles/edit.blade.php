@extends('layouts.app')

@section('content')
@if(isset($sample))
    @php
    $samples = [
        1 => ['name' => 'Administrator', 'description' => 'Maintains tracker records and user access.', 'permissions' => ['View projects', 'Edit projects', 'Update activities', 'Manage issues', 'Resolve issues', 'Record meetings', 'Upload documents', 'Download reports', 'Archive records', 'Manage users', 'Manage reference lists']],
        2 => ['name' => 'Section Officer', 'description' => 'Coordinates projects and follow-up work.', 'permissions' => ['View projects', 'Edit projects', 'Update activities', 'Manage issues', 'Record meetings', 'Upload documents']],
        3 => ['name' => 'Donor Viewer', 'description' => 'Views assigned projects and permitted reports.', 'permissions' => ['View projects', 'Download reports']],
    ];
@endphp
    @php
        abort_unless(isset($samples[$sample]), 404);
        $record = new \App\Models\TrackerRecord(['module' => 'roles', 'data' => $samples[$sample]]);
    @endphp
    <p class="mb-4 text-sm text-slate-500">Example record. Editing saves a new record; the example stays available.</p>
@endif
<div class="mb-5 flex items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">Edit {{ strtolower($definition['singular']) }}</h1>
    <a class="text-sm font-semibold text-teal" href="{{ route('roles.index') }}">Back to list</a>
</div>
@include('roles.partials.form')
@endsection

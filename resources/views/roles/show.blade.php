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

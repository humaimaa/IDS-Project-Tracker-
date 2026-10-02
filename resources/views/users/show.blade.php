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
    <p class="mb-4 text-sm text-slate-500">Example record.</p>
@endif
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">{{ $record->data['name'] }}</h1>
    <div class="flex items-center gap-4"><a class="text-sm text-teal" href="{{ route($module.'.index') }}">Back to list</a><a class="rounded-lg bg-teal px-4 py-2 text-sm text-white" href="{{ isset($sample) ? route($module.'.sample-edit', $sample) : route($module.'.edit', $record) }}">Edit {{ strtolower($definition['singular']) }}</a></div>
</div>
<dl class="panel grid gap-6 p-6 sm:grid-cols-2">
    @foreach($definition['fields'] as $field)<div><dt class="text-xs font-semibold text-slate-500">{{ $field[1] }}</dt><dd class="mt-2 whitespace-pre-wrap break-words text-sm">{{ is_array($record->data[$field[0]] ?? null) ? implode(', ', $record->data[$field[0]]) : ($record->data[$field[0]] ?? '—') }}</dd></div>@endforeach
</dl>
<section class="panel mt-5 p-6">
    <h2 class="font-semibold">Assigned role permissions</h2>
    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm">
        @forelse($assignedRole?->data['permissions'] ?? [] as $permission)
            <li>{{ $permission }}</li>
        @empty
            <li>No permissions assigned.</li>
        @endforelse
    </ul>
</section>
@endsection

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
    <p class="mb-4 text-sm text-slate-500">Example record.</p>
@endif
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">{{ $record->data['name'] }}</h1>
    <div class="flex items-center gap-4"><a class="text-sm text-teal" href="{{ route($module.'.index') }}">Back to list</a><a class="rounded-lg bg-teal px-4 py-2 text-sm text-white" href="{{ isset($sample) ? route($module.'.sample-edit', $sample) : route($module.'.edit', $record) }}">Edit {{ strtolower($definition['singular']) }}</a></div>
</div>
<dl class="panel grid gap-6 p-6 sm:grid-cols-2">
    @foreach($definition['fields'] as $field)<div><dt class="text-xs font-semibold text-slate-500">{{ $field[1] }}</dt><dd class="mt-2 whitespace-pre-wrap break-words text-sm">{{ is_array($record->data[$field[0]] ?? null) ? implode(', ', $record->data[$field[0]]) : ($record->data[$field[0]] ?? '—') }}</dd></div>@endforeach
</dl>
<section class="panel mt-5 space-y-4 p-6">
    <h2 class="font-display text-lg font-bold">Follow-up actions</h2>
    @forelse($record->data['actions'] ?? [] as $action)
        <article class="space-y-2 rounded-lg border border-slate-200 p-4">
            <h3 class="font-semibold">{{ $action['description'] }}</h3>
            <p class="text-sm">{{ $action['officer'] }} · Due {{ $action['due'] }} · {{ $action['status'] }}</p>
            @foreach(['remarks' => 'Completion remarks', 'activity' => 'Related activity', 'issue' => 'Related issue'] as $key => $label)
                @if(!empty($action[$key]))<p class="text-sm text-slate-600">{{ $label }}: {{ $action[$key] }}</p>@endif
            @endforeach
        </article>
    @empty<p class="text-sm text-slate-500">No follow-up actions recorded.</p>@endforelse
</section>
@include('partials.documents-show')
@endsection

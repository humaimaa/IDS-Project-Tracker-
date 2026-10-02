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
    <p class="mb-4 text-sm text-slate-500">Example record.</p>
@endif
@if($dashboardDemo ?? false)
    <p class="mb-4 text-sm text-slate-500">Demo data · {{ $record->data['health'] }}</p>
    @if($record->data['reasons'])<div class="mb-4 rounded-lg bg-rose-50 p-4 text-sm text-rose-700"><h2 class="font-semibold">Project requires attention</h2><ul class="mt-2 space-y-1">@foreach($record->data['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul></div>@endif
@endif
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">{{ $record->data['name'] }}</h1>
    <div class="flex items-center gap-4"><a class="text-sm text-teal" href="{{ ($dashboardDemo ?? false) ? route('dashboard') : route($module.'.index') }}">Back to list</a>@unless($dashboardDemo ?? false)<a class="rounded-lg bg-teal px-4 py-2 text-sm text-white" href="{{ isset($sample) ? route($module.'.sample-edit', $sample) : route($module.'.edit', $record) }}">Edit {{ strtolower($definition['singular']) }}</a>@endunless</div>
</div>
<dl class="panel grid gap-6 p-6 sm:grid-cols-2">
    @foreach($definition['fields'] as $field)<div><dt class="text-xs font-semibold text-slate-500">{{ $field[1] }}</dt><dd class="mt-2 whitespace-pre-wrap break-words text-sm">{{ is_array($record->data[$field[0]] ?? null) ? implode(', ', $record->data[$field[0]]) : ($record->data[$field[0]] ?? '—') }}</dd></div>@endforeach
</dl>
<section class="panel mt-5 space-y-4 p-6">
    <h2 class="font-display text-lg font-bold">Project activities</h2>
    @if($record->exists)
        <div class="flex flex-wrap gap-4 text-sm text-teal">
            <a href="{{ route('activities.index', ['project_id' => $record->id]) }}">View activities</a>
            <a href="{{ route('subactivities.index', ['project_id' => $record->id]) }}">View sub-activities</a>
        </div>
        @foreach($projectActivities->groupBy('stage') as $stage => $items)
            <h3 class="font-semibold text-teal">{{ $stage }}</h3>
            @foreach($items as $item)
                <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-3 text-sm">
                    <a class="text-teal" href="{{ route('activities.show', $item) }}">{{ $item->data['name'] }}</a>
                    <span>{{ $item->data['status'] ?? 'Not Started' }}</span>
                </div>
            @endforeach
        @endforeach
    @endif
</section>
@include('partials.documents-show')
@endsection

@extends('layouts.app')

@section('content')
@if(isset($sample))
    @php
    $samples = config('meeting_samples');
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

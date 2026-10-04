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
    <p class="mb-4 text-sm text-slate-500">Example record. Editing saves a new record; the example stays available.</p>
@endif
<div class="mb-5 flex items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">Edit {{ strtolower($definition['singular']) }}</h1>
    <a class="text-sm font-semibold text-teal" href="{{ route('meetings.index') }}">Back to list</a>
</div>
@include('meetings.partials.form')
@endsection

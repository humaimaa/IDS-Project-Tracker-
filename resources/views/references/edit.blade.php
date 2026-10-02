@extends('layouts.app')

@section('content')
@if(isset($sample))
    @php
    $samples = [
        1 => ['name' => 'Peshawar', 'type' => 'District', 'status' => 'Active'],
        2 => ['name' => 'Education', 'type' => 'Sector', 'status' => 'Active'],
        3 => ['name' => 'Asian Development Bank', 'type' => 'Development partner', 'status' => 'Active'],
    ];
@endphp
    @php
        abort_unless(isset($samples[$sample]), 404);
        $record = new \App\Models\TrackerRecord(['module' => 'references', 'data' => $samples[$sample]]);
    @endphp
    <p class="mb-4 text-sm text-slate-500">Example record. Editing saves a new record; the example stays available.</p>
@endif
<div class="mb-5 flex items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">Edit {{ strtolower($definition['singular']) }}</h1>
    <a class="text-sm font-semibold text-teal" href="{{ route('references.index') }}">Back to list</a>
</div>
@include('references.partials.form')
@endsection

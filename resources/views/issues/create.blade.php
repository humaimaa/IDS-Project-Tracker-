@extends('layouts.app')

@section('content')
<div class="mb-5 flex items-center justify-between gap-3">
    <h1 class="font-display text-2xl font-extrabold">Add {{ strtolower($definition['singular']) }}</h1>
    <a class="text-sm font-semibold text-teal" href="{{ route('issues.index') }}">Back to list</a>
</div>
@include('issues.partials.form')
@endsection

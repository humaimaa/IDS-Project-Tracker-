@extends('layouts.app')
@section('content')
<section class="portfolio-page space-y-5">
    <a class="text-sm font-semibold text-teal" href="{{ route('dashboard') }}">← Dashboard</a>
    <div class="flex flex-wrap items-center justify-between gap-3"><div><h1 class="font-display text-2xl font-extrabold">Manage projects</h1><p class="mt-2 text-sm text-slate-500">{{ $records->total() }} projects · Select a project to view its details</p></div><a class="portfolio-primary" href="{{ route('projects.create') }}">+ Add project</a></div>
    <form class="panel grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-4" method="get" action="{{ route('projects.index') }}">
        <label class="text-xs font-semibold text-slate-500">Search<input class="field mt-2" name="search" value="{{ request('search') }}" placeholder="Project name or reference"></label>
        <label class="text-xs font-semibold text-slate-500">Development partner<select class="field mt-2" name="partner"><option value="">All partners</option>@foreach($all->flatMap(fn ($row) => (array) ($row['project']->data['partners'] ?? []))->unique()->sort() as $value)<option @selected(request('partner') === $value)>{{ $value }}</option>@endforeach</select></label>
        @foreach(['sector', 'district', 'phase'] as $filter)
            @if(request()->filled($filter))<input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">@endif
        @endforeach
        <label class="text-xs font-semibold text-slate-500">Status<select class="field mt-2" name="health"><option value="">All statuses</option>@foreach(['Off track', 'On track'] as $value)<option @selected(request('health') === $value)>{{ $value }}</option>@endforeach</select></label>
        <div class="flex items-end gap-4"><button class="portfolio-primary">Apply filters</button><a class="py-3 text-xs font-semibold text-teal" href="{{ route('projects.index') }}">Reset</a></div>
    </form>
    @if(request()->hasAny(['partner','sector','district','phase','health']))<div class="flex flex-wrap gap-2">@foreach(request()->only(['partner','sector','district','phase','health']) as $key => $value)@if($value)<a class="portfolio-pill" href="{{ route('projects.index', request()->except([$key, 'page'])) }}">{{ ucfirst($key) }}: {{ $value }} ×</a>@endif @endforeach</div>@endif
    <section class="panel overflow-hidden">@include('projects.partials.portfolio-table', ['projectRows' => $records])</section>
    {{ $records->links() }}
</section>
@endsection

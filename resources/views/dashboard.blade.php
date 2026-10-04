@extends('layouts.app')
@section('content')
<section class="portfolio-page space-y-6" data-dashboard-updates="{{ route('dashboard.updates') }}">
    <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="eyebrow mb-2">International Development Section</p><h1 class="font-display text-2xl font-extrabold">Project Overview</h1></div><span class="portfolio-pill">{{ $rows->contains('demo', true) ? 'Demo data' : 'Live project portfolio' }}</span></div>
    <div class="dashboard-top-row"><div class="portfolio-cards dashboard-three-cards">
        @foreach(array_slice($cards, 0, 3) as $card)<a class="portfolio-card" data-tone="{{ $card['tone'] }}" href="{{ route('projects.index', $card['filter']) }}"><span class="portfolio-card-label">{{ $card['label'] }}</span><strong>{{ $card['count'] }}</strong><span class="portfolio-card-link">View projects <span aria-hidden="true">↗</span></span></a>@endforeach
    </div>
        <aside class="panel dashboard-discussion-panel" aria-label="Latest project discussions"><div class="flex items-center justify-between gap-3 px-5 pt-5"><h2 class="font-display text-sm font-bold">Unseen comments & replies</h2><span class="text-[10px] text-slate-400">Live updates</span></div><div data-dashboard-comments>@include('partials.dashboard-comments')</div></aside>
    </div>
    <div data-dashboard-meetings>@include('partials.dashboard-meetings')</div>
    <div class="grid gap-5 lg:grid-cols-2">
        @foreach(array_intersect_key($charts, array_flip(['partner', 'sector'])) as $filter => $chart)
        @include('partials.portfolio-chart')
        @endforeach
    </div>
    <div class="district-meeting-row">
        @include('partials.portfolio-chart', ['filter' => 'district', 'chart' => $charts['district']])
        <aside class="panel min-w-0 p-5"><div class="mb-5 flex items-center justify-between gap-3"><h2 class="font-display text-base font-bold">Upcoming meetings</h2><a class="text-xs font-semibold text-teal" href="{{ route('meetings.index') }}">View all &rarr;</a></div><div data-dashboard-upcoming>@include('partials.dashboard-upcoming')</div></aside>
    </div>
    <section class="panel p-5 sm:p-6" aria-labelledby="flagship-projects-heading">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><div><h2 id="flagship-projects-heading" class="font-display text-base font-bold">Flagship projects</h2><p class="mt-1 text-xs text-slate-500">Priority projects at a glance</p></div><span class="portfolio-pill">{{ $flagshipProjects->count() }} projects{{ $rows->contains('demo', true) ? ' / Demo data' : '' }}</span></div>
        <div class="grid gap-4 lg:grid-cols-3">
        @forelse($flagshipProjects as $row)
            <a href="{{ $row['url'] }}" class="panel block border-t-4 border-teal p-5 transition hover:bg-teal-50">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><span class="text-xs font-bold text-teal">Flagship project</span><span class="portfolio-status" data-status="{{ $row['health'] }}">{{ $row['health'] }}</span></div>
                <h3 class="font-display text-base font-bold">{{ $row['project']->data['name'] }}</h3>
                <p class="mt-2 text-xs text-slate-400">{{ $row['project']->data['reference'] ?? '' }}</p>
                <dl class="mt-4 space-y-2 text-xs"><div><dt class="text-slate-400">Stage</dt><dd class="mt-1 font-semibold">{{ $row['phase'] }}</dd></div><div><dt class="text-slate-400">Development partner</dt><dd class="mt-1">{{ implode(', ', (array) ($row['project']->data['partners'] ?? [])) ?: 'Not assigned' }}</dd></div><div><dt class="text-slate-400">District</dt><dd class="mt-1">{{ implode(', ', (array) ($row['project']->data['districts'] ?? [])) ?: 'Not assigned' }}</dd></div></dl>
                <span class="mt-5 block text-xs font-semibold text-teal">View project &rarr;</span>
            </a>
        @empty<p class="text-sm text-slate-500">No projects are marked as flagship projects yet.</p>@endforelse
        </div>
    </section>
</section>
@endsection

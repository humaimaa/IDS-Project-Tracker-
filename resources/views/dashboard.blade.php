@extends('layouts.app')
@section('content')
<section id="dashboard-page" class="dashboard-reference space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="mt-1 font-display text-[26px] font-extrabold tracking-tight text-ink">Project Overview</h1></div>
        <span class="rounded-md bg-teal/10 px-2 py-1 text-[10px] text-teal">Demo data</span>
    </div>
    <form method="get" action="{{ route('dashboard') }}" class="dashboard-filters grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_auto]" data-dashboard-filters>
        @foreach(['stage' => ['Stage', 'stage'], 'partner' => ['Development partner', 'partners'], 'sector' => ['Sector', 'sectors'], 'district' => ['District', 'districts']] as $filter => [$label, $field])
            <label class="text-xs font-semibold text-slate-500">{{ $label }}<select class="field mt-2" name="{{ $filter }}"><option value="">All {{ ['stage' => 'Stages', 'partner' => 'Partners', 'sector' => 'Sectors', 'district' => 'Districts'][$filter] }}</option>@foreach($fieldOptions[$field] as $option)<option @selected(request($filter) === $option)>{{ $option }}</option>@endforeach</select></label>
        @endforeach
        <input type="hidden" name="search" value="{{ request('search') }}">
        <a class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-teal px-4 text-sm font-semibold text-white" href="{{ route('projects.create') }}"><span class="text-xl">＋</span> Add Project</a>
        <noscript><button class="rounded-lg bg-teal px-4 py-2 text-white">Apply filters</button></noscript>
        @if(request()->hasAny(['stage', 'partner', 'sector', 'district', 'search', 'health']))<a class="text-xs text-teal" href="{{ route('dashboard') }}">Clear filters</a>@endif
    </form>
    <div class="dashboard-summary-row grid gap-3">
        @foreach(['Total projects' => $rows->count(), ...$stageCounts->all()] as $label => $count)
            <a class="panel dashboard-summary flex items-center gap-4 p-5" href="{{ route('dashboard', array_merge(request()->except(['health', 'stage']), $label === 'Total projects' ? [] : ['stage' => $label])) }}">
                <span class="summary-icon grid size-14 shrink-0 place-items-center rounded-full {{ $loop->index === 2 ? 'bg-sky-100 text-sky-500' : 'bg-teal/10 text-teal' }}" aria-hidden="true">
                    <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        @if($loop->index === 0)<path d="M3 7V5h6l2 2h10v13H3z" />
                        @elseif($loop->index === 1)<path d="m12 3 9 5-9 5-9-5 9-5Zm-9 9 9 5 9-5m-18 5 9 5 9-5" />
                        @elseif($loop->index === 2)<path d="M14 2H5v20h14V7zM14 2v6h5M8 12h8m-8 4h8" />
                        @else<circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3M5 5l2 2m10 10 2 2M5 19l2-2M17 7l2-2" />@endif
                    </svg>
                </span>
                <div class="flex-1"><p class="text-xs text-slate-500">{{ $label === 'Concept' ? 'Concept Stage' : $label }}</p><p class="mt-1 font-display text-3xl font-extrabold">{{ $count }}</p></div><span class="text-slate-400">›</span>
            </a>
        @endforeach
    </div>
    <div class="dashboard-stage-status grid gap-4">
        <article class="panel stage-chart-panel">
            <h2 class="font-display text-base font-bold">Projects by Stage</h2>
            <svg class="stage-chart" viewBox="0 0 450 150" role="img" aria-label="Project counts by stage">
                @foreach([0, 10, 20, 30] as $tick)
                    <line x1="35" y1="{{ 120 - $tick * 3.5 }}" x2="445" y2="{{ 120 - $tick * 3.5 }}" stroke="#e5edf0" />
                    <text x="25" y="{{ 124 - $tick * 3.5 }}" text-anchor="end" fill="#536581" font-size="11">{{ $tick }}</text>
                @endforeach
                <line x1="35" y1="15" x2="35" y2="120" stroke="#cbd5e1" />
                @foreach([...$stageCounts->all(), 'Completion' => 0] as $stage => $count)
                    <rect x="{{ 47 + $loop->index * 101 }}" y="{{ 120 - $count * 3.5 }}" width="78" height="{{ $count * 3.5 }}" rx="2" fill="{{ ['#50bec2', '#79bff0', '#36a387', '#a5b4c5'][$loop->index] }}" />
                    <text x="{{ 86 + $loop->index * 101 }}" y="{{ 110 - $count * 3.5 }}" text-anchor="middle" fill="#102c3c" font-size="13" font-weight="700">{{ $count }}</text>
                    <text x="{{ 86 + $loop->index * 101 }}" y="140" text-anchor="middle" fill="#536581" font-size="10">{{ $stage === 'Concept' ? 'Concept Stage' : $stage }}</text>
                @endforeach
            </svg>
        </article>
        @foreach($healthCounts as $health => $count)
            <a class="panel dashboard-health-card flex flex-col justify-center {{ $selectedHealth === $health ? 'ring-2 ring-teal' : '' }}" href="{{ route('dashboard', array_merge(request()->except('health'), ['health' => $health])) }}#project-directory">
                <span class="summary-icon grid shrink-0 place-items-center rounded-full {{ $health === 'Delayed' ? 'bg-rose-50 text-rose-600' : ($health === 'On hold' ? 'bg-amber-50 text-amber-700' : 'bg-teal/10 text-teal') }}" aria-hidden="true">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/>@if($health === 'Delayed')<path d="M12 7v6m0 4h.01"/>@elseif($health === 'On hold')<path d="M9 8v8m6-8v8"/>@else<path d="m7 12 3 3 7-7"/>@endif</svg>
                </span>
                <div class="min-w-0 flex-1"><p class="text-xs text-slate-500">{{ $health }}</p><p class="mt-1 font-display text-3xl font-extrabold">{{ $count }}</p></div><span class="text-slate-400">›</span>
            </a>
        @endforeach
    </div>
    <div class="dashboard-charts grid gap-4 md:grid-cols-2">
        @foreach($distributions as $label => $counts)
            <article class="panel p-5"><h2 class="font-display text-base font-bold">{{ $label }}</h2><div class="mt-6 space-y-5">
                @foreach($counts as $name => $count)
                    <div class="grid grid-cols-[110px_1fr_24px] items-center gap-3 text-xs">
                        <span class="flex items-center gap-2 text-slate-600">@if($label === 'Sectors')<span class="sector-icon" style="color: {{ ['#229b7a', '#ef5967', '#7e75bd', '#2999dc'][$loop->index % 4] }}; background: {{ ['#e0f5ed', '#ffe5e8', '#ede9fa', '#e0f2ff'][$loop->index % 4] }}" aria-hidden="true">{{ ['▤', '♥', '▥', '◉'][$loop->index % 4] }}</span>@endif{{ $name }}</span>
                        <div class="h-5"><div class="h-full rounded-r-sm" style="width: {{ $count / max(1, $counts->max()) * 100 }}%; background: {{ ($label === 'Sectors' ? ['#36aa86', '#42b9be', '#7d83df', '#79bff0'] : ['#0ba9a6', '#5997e8', '#d4dce9'])[$loop->index % ($label === 'Sectors' ? 4 : 3)] }}"></div></div>
                        <strong>{{ $count }}</strong>
                    </div>
                @endforeach
            </div></article>
        @endforeach
    </div>
    <article class="panel overflow-hidden">
        <div class="flex items-center justify-between gap-3 p-5"><h2 class="font-display text-sm font-bold"><span class="section-icon" aria-hidden="true">!</span>Projects requiring attention</h2><span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-600">{{ $healthCounts['Delayed'] }} delayed</span></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead><tr><th class="p-3">Project no.</th><th class="p-3">Project</th><th class="p-3">Stage</th><th class="p-3">Reason for delay</th><th class="p-3">Responsible officer</th></tr></thead><tbody>
            @forelse($rows->where('health', 'Delayed') as $row)
                @php($projectUrl = $row['project']->exists ? route('projects.show', $row['project']) : route('dashboard.projects.show', $row['project']->data['reference']))
                <tr class="table-row dashboard-project-row" data-project-url="{{ $projectUrl }}"><td class="table-cell"><a class="font-semibold text-teal" href="{{ $projectUrl }}">{{ $row['project']->data['reference'] ?? $row['project']->id }}</a></td><td class="table-cell">{{ $row['project']->data['name'] }}</td><td class="table-cell"><span class="stage-badge" data-stage="{{ $row['project']->data['stage'] ?? '' }}">{{ $row['project']->data['stage'] ?? '—' }}</span></td><td class="table-cell"><ul class="space-y-1">@foreach($row['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul></td><td class="table-cell">{{ $row['project']->data['officer'] ?? '—' }}</td></tr>
            @empty<tr><td colspan="5" class="p-8 text-center text-slate-400">No delayed projects match these filters.</td></tr>@endforelse
        </tbody></table></div>
    </article>
    <div class="grid gap-4 xl:grid-cols-[1.55fr_1fr]">
    <article class="panel p-5"><div class="mb-3 flex items-center justify-between"><h2 class="font-display text-base font-bold"><span class="section-icon" aria-hidden="true">▤</span>Recent Updates</h2><a class="text-xs text-teal" href="{{ route('activities.index') }}">View all →</a></div>
        <ul class="divide-y divide-slate-100">@foreach($demo['updates'] ?? [] as $update)<li class="flex items-start gap-3 py-4"><span class="mt-1.5 size-2 shrink-0 rounded-full bg-teal"></span><span class="flex-1 text-xs leading-5 text-slate-600">{{ $update['text'] }}</span><span class="text-[10px] text-slate-400">{{ $update['time'] }}</span></li>@endforeach</ul>
    </article>
        <article class="panel p-5"><div class="flex items-center justify-between"><h2 class="font-display text-sm font-bold"><span class="section-icon" aria-hidden="true">▦</span>Upcoming meetings</h2><a class="text-xs text-teal" href="{{ route('meetings.index') }}">View all →</a></div><div class="mt-4 space-y-4">
            @forelse($meetings as $meeting)<a class="dashboard-meeting block border-l-2 border-teal py-2 pl-3" href="{{ route('meetings.index') }}"><p class="text-sm font-bold">{{ $meeting->data['name'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $meeting->data['description'] ?? '' }}</p><p class="mt-2 text-xs text-slate-500">{{ str_replace('T', ' · ', $meeting->data['date']) }}</p></a>@empty<p class="py-6 text-sm text-slate-400">No upcoming meetings.</p>@endforelse
        </div></article>
    </div>
    <div class="grid gap-4">
    <article class="panel overflow-hidden" id="project-directory">
        <div class="flex flex-wrap items-center justify-between gap-3 p-5"><div><h2 class="font-display text-sm font-bold">{{ $selectedHealth ? $selectedHealth.' projects' : 'Project Directory (Recent Projects)' }}</h2><p class="mt-1 text-xs text-slate-500">{{ $directory->count() }} projects · Select a status card to view its projects.</p></div><a class="text-xs text-teal" href="{{ route('projects.index') }}">Manage projects →</a></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-3">Project no.</th><th class="p-3">Project</th><th class="p-3">Development partner</th><th class="p-3">Sector</th><th class="p-3">Stage</th><th class="p-3">Status</th></tr></thead><tbody>
            @forelse($directory->take(4) as $row)
            @php($projectUrl = $row['project']->exists ? route('projects.show', $row['project']) : route('dashboard.projects.show', $row['project']->data['reference']))
            <tr class="table-row dashboard-project-row" data-project-url="{{ $projectUrl }}"><td class="table-cell"><a class="font-semibold text-teal" href="{{ $projectUrl }}">{{ $row['project']->data['reference'] ?? '—' }}</a></td><td class="table-cell">{{ $row['project']->data['name'] }}</td><td class="table-cell">@foreach($row['project']->data['partners'] ?? [] as $partner)<span class="partner-badge" data-partner="{{ $partner }}">{{ $partner }}</span>@endforeach</td><td class="table-cell">@foreach($row['project']->data['sectors'] ?? [] as $sector)<span class="sector-badge" data-sector="{{ $sector }}">{{ $sector }}</span>@endforeach</td><td class="table-cell"><span class="stage-badge" data-stage="{{ $row['project']->data['stage'] ?? '' }}">{{ $row['project']->data['stage'] ?? '—' }}</span></td><td class="table-cell"><span class="whitespace-nowrap rounded-full px-2 py-1 {{ $row['health'] === 'Delayed' ? 'bg-rose-50 text-rose-600' : ($row['health'] === 'On hold' ? 'bg-amber-50 text-amber-700' : 'bg-teal/10 text-teal') }}">{{ $row['health'] }}</span></td></tr>@empty<tr><td colspan="6" class="p-8 text-center text-slate-400">No projects match these filters.</td></tr>@endforelse
        </tbody></table></div>
    </article>
    </div>
</section>
@endsection

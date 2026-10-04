@php
$palette = ['#0d9488', '#5b8def', '#a16bd1', '#e49a36', '#db6c86', '#54a478', '#5c9fb4', '#b98459'];
@endphp
<section class="panel p-5 sm:p-6">
    <div class="mb-6 flex items-center justify-between gap-4"><h2 class="font-display text-base font-bold">{{ $chart['title'] }}</h2><span class="text-xs text-slate-400">{{ $filter === 'partner' ? 'Select a slice or donor' : ($filter === 'district' ? 'Select a district to explore' : 'Select a bar to explore') }}</span></div>
    @if($filter === 'partner' && $chart['counts']->sum() > 0)
    @php
$offset = 0;
@endphp
    <div class="donor-pie-layout">
        <svg class="donor-pie" viewBox="0 0 220 220" role="group" aria-label="Project distribution by donor; select a slice to view projects">
            @foreach($chart['counts'] as $label => $count)
                @php
$share = $count / $chart['counts']->sum() * 100;
@endphp
                @php
                    $startAngle = deg2rad($offset * 3.6 - 90);
                    $endAngle = deg2rad(($offset + $share) * 3.6 - 90);
                    $startX = 110 + 94 * cos($startAngle);
                    $startY = 110 + 94 * sin($startAngle);
                    $endX = 110 + 94 * cos($endAngle);
                    $endY = 110 + 94 * sin($endAngle);
                @endphp
                <a href="{{ route('projects.index', ['partner' => $label]) }}" aria-label="{{ $label }}: {{ $count }} projects">
                    @if($share >= 100)<circle cx="110" cy="110" r="94" fill="{{ $palette[$loop->index % count($palette)] }}"><title>{{ $label }}: {{ $count }} projects</title></circle>
                    @else<path d="M 110 110 L {{ $startX }} {{ $startY }} A 94 94 0 {{ $share > 50 ? 1 : 0 }} 1 {{ $endX }} {{ $endY }} Z" fill="{{ $palette[$loop->index % count($palette)] }}" stroke="white" stroke-width="2"><title>{{ $label }}: {{ $count }} projects</title></path>@endif
                </a>
                @php
$offset += $share;
@endphp
            @endforeach
        </svg>
        <div class="sector-legend">@foreach($chart['counts'] as $label => $count)<a href="{{ route('projects.index', ['partner' => $label]) }}"><span class="chart-color-dot" style="background: {{ $palette[$loop->index % count($palette)] }}"></span><span>{{ $label }}</span><strong>{{ $count }}</strong></a>@endforeach</div>
    </div>
    @elseif($filter === 'district')
    <div class="mb-4 flex flex-wrap justify-between gap-2 text-xs text-slate-500"><span>{{ $chart['counts']->count() }} districts &middot; Ranked by project count</span><span>Scroll to view all districts</span></div>
    <div class="district-ranked-chart" tabindex="0" role="region" aria-label="District project counts, highest to lowest. Scroll to view all districts.">
        <div class="district-chart-axis" aria-hidden="true"><span>District</span><span>Number of projects</span><span>Total</span></div>
        <div class="district-chart-axis district-chart-axis-copy" aria-hidden="true"><span>District</span><span>Number of projects</span><span>Total</span></div>
        @forelse($chart['counts']->sortDesc() as $label => $count)
        <a class="portfolio-bar" href="{{ route('projects.index', ['district' => $label]) }}" aria-label="{{ $label }}: {{ $count }} projects"><span class="portfolio-bar-label">{{ $label }}</span><span class="portfolio-bar-track"><span style="width: {{ $count / max(1, $chart['counts']->max()) * 100 }}%; background: {{ $palette[$loop->index % count($palette)] }}"></span></span><strong>{{ $count }}</strong></a>
        @empty<p class="py-6 text-sm text-slate-500">No district data available.</p>@endforelse
    </div>
    @else
    <div class="portfolio-bars {{ $filter === 'district' ? 'district-paired-bars' : '' }}">
        @forelse($chart['counts'] as $label => $count)<a class="portfolio-bar" href="{{ route('projects.index', [$filter => $label]) }}" aria-label="{{ $label }}: {{ $count }} projects"><span class="portfolio-bar-label">{{ $label }}</span><span class="portfolio-bar-track"><span style="width: {{ $count / max(1, $chart['counts']->max()) * 100 }}%; background: {{ $palette[$loop->index % count($palette)] }}"></span></span><strong>{{ $count }}</strong></a>@empty<p class="text-sm text-slate-500">No {{ $filter }} data available.</p>@endforelse
    </div>
    @endif
    <p class="mt-5 text-xs text-slate-400">Projects covering multiple {{ $filter === 'district' ? 'districts' : ($filter === 'sector' ? 'sectors' : 'partners') }} appear in each applicable category.</p>
</section>

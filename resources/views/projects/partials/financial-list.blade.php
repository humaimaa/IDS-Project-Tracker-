<nav class="flex gap-4 text-sm" aria-label="Financial information archive">
    <a class="text-teal" href="{{ route('financial-reports.index', request()->except(['archived', 'page'])) }}" @if(!request()->boolean('archived')) aria-current="page" @endif>Active</a>
    <a class="text-teal" href="{{ route('financial-reports.index', array_merge(request()->except('page'), ['archived' => 1])) }}" @if(request()->boolean('archived')) aria-current="page" @endif>Archived</a>
</nav>
@if(request()->boolean('archived'))<p class="text-sm text-slate-500">Financial information is archived with its project. Restore the project to edit it again.</p>@endif
<section class="panel overflow-x-auto">
    <table class="portfolio-table">
        <thead><tr><th>Project</th>@if(auth()->user()->hasPortfolioPermission('project-costs.view'))<th>Project cost</th>@endif<th>Financial year</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($records as $record)
                <tr data-project-url="{{ route('financial-reports.show', $record) }}">
                    <td><a class="font-semibold text-teal" href="{{ route('financial-reports.show', $record) }}">{{ $record->project->name }}</a><span class="mt-1 block text-xs text-slate-500">{{ $record->currency_code }}</span></td>
                    @if(auth()->user()->hasPortfolioPermission('project-costs.view'))
                        <td>@forelse($record->project->costVersions->unique('currency_code') as $cost)<span class="block whitespace-nowrap">{{ $cost->currency_code }} {{ number_format((float) $cost->total_cost, 2) }}</span>@empty<span class="text-slate-500">Not supplied</span>@endforelse</td>
                    @endif
                    <td>{{ $record->financialYear->label }}</td>

                    <td>@if(!$record->project->archived_at && auth()->user()->hasPortfolioPermission('financial-reports.update'))<a class="portfolio-primary" href="{{ route('financial-information.edit', $record) }}">Edit</a>@else<span class="text-xs text-slate-500">Read only</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="{{ auth()->user()->hasPortfolioPermission('project-costs.view') ? 4 : 3 }}" class="text-slate-500">No {{ request()->boolean('archived') ? 'archived' : 'active' }} financial information.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>

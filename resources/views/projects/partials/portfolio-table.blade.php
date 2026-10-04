<div class="overflow-x-auto">
<table class="portfolio-table"><thead><tr><th>Project</th><th>Stage</th><th>Development partner</th><th>{{ ($showIssues ?? false) ? 'Issues requiring attention' : 'District / sector' }}</th><th>Status</th></tr></thead><tbody>
@forelse($projectRows as $row)
<tr data-project-url="{{ $row['url'] }}"><td><a class="font-semibold text-teal" href="{{ $row['url'] }}">{{ $row['project']->data['name'] }}</a><small>{{ $row['project']->data['reference'] ?? 'Reference not set' }}{{ $row['demo'] ? ' · Demo' : '' }}</small></td><td><span class="portfolio-pill">{{ $row['phase'] === 'Pipeline' ? '1 · Pipeline' : '2 · Ongoing' }}</span></td><td>{{ implode(', ', (array) ($row['project']->data['partners'] ?? [])) ?: 'Not assigned' }}</td><td>@if($showIssues ?? false)<ul class="space-y-1">@foreach($row['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>@else{{ implode(', ', (array) ($row['project']->data['districts'] ?? [])) ?: 'Not set' }}<small>{{ implode(', ', (array) ($row['project']->data['sectors'] ?? [])) }}</small>@endif</td><td><span class="portfolio-status" data-status="{{ $row['health'] }}">{{ $row['health'] }}</span></td></tr>
@empty<tr><td colspan="5" class="!py-10 text-center text-slate-500">No projects match this selection.</td></tr>@endforelse
</tbody></table>
</div>

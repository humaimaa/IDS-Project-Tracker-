@forelse($issueProjects as $issueProject)
<a class="issue-project-item" href="{{ $issueProject['url'] }}"><span class="issue-project-dot" aria-hidden="true"></span><span class="min-w-0"><strong>{{ $issueProject['project']->data['name'] }}</strong><small>{{ count($issueProject['reasons']) }} {{ count($issueProject['reasons']) === 1 ? 'issue' : 'issues' }} · {{ $issueProject['phase'] }}</small></span><span class="ml-auto shrink-0" aria-hidden="true">&rarr;</span></a>
@empty<p class="p-6 text-center text-sm text-slate-500">No projects have open issues.</p>@endforelse

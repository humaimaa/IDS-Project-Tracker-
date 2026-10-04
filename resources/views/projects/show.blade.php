@extends('layouts.app')

@section('content')
<div class="project-workspace space-y-5">
    <a class="inline-flex items-center gap-2 text-sm font-semibold text-teal hover:underline" href="{{ route('projects.index') }}"><span aria-hidden="true">&larr;</span> Back to projects</a>
    <section class="project-hero">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <div class="mb-3 flex flex-wrap items-center gap-2 text-xs"><span class="rounded-md bg-white/15 px-2.5 py-1 font-semibold">{{ $record->data['reference'] ?? 'Project tracking' }}</span><span class="text-teal-100">{{ $sample ? 'Example record' : ($record->data['status'] ?? 'Active') }}</span></div>
                <h1 class="font-display text-xl font-extrabold leading-snug sm:text-2xl"><a class="rounded hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white" href="{{ $detailsUrl }}">{{ $record->data['name'] }} <span class="inline-block text-teal-200" aria-hidden="true">&rarr;</span></a></h1>
                <p class="mt-2 text-sm text-teal-100/80">{{ $record->data['agency'] ?? 'Implementing agency not assigned' }} <span class="mx-2" aria-hidden="true">·</span> {{ $record->data['stage'] ?? 'Concept' }}</p>
            </div>
            <a class="shrink-0 rounded-lg border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold transition hover:bg-white/20" href="{{ $detailsUrl }}">Project details <span aria-hidden="true">&rarr;</span></a>
        </div>
        <div class="mt-6 border-t border-white/15 pt-4">
            <div class="mb-2 flex justify-between gap-4 text-xs"><span class="font-semibold text-teal-100">Activity completion</span><span>{{ $summary['completed'] }} completed · {{ $progress }}%</span></div>
            <div class="h-2 overflow-hidden rounded-full bg-black/15" role="progressbar" aria-label="Activity completion" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-teal-300" style="width: {{ $progress }}%"></div></div>
        </div>
    </section>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach(['total' => ['Total activities', 'Across all three stages', 'teal'], 'on_track' => ['On-track activities', 'Within the planned deadline', 'green'], 'off_track' => ['Off-track activities', 'Overdue or on hold', 'red'], 'completed' => ['Completed activities', 'Finished work', 'blue']] as $key => [$label, $hint, $tone])
            <article class="tracking-stat tracking-stat-{{ $tone }}"><p class="text-xs font-semibold text-slate-500">{{ $label }}</p><p class="mt-2 font-display text-3xl font-extrabold text-ink">{{ $summary[$key] }}</p><p class="mt-2 text-[11px] text-slate-500">{{ $hint }}</p></article>
        @endforeach
    </div>
    @if($summary['off_track'])<div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"><span class="font-bold" aria-hidden="true">!</span><p><strong>{{ $summary['off_track'] }} {{ $summary['off_track'] === 1 ? 'activity needs' : 'activities need' }} attention.</strong> Review the deadlines and remarks below.</p></div>@endif
    <div class="project-tracking-grid">
        <section class="panel min-w-0 overflow-hidden" aria-labelledby="activity-plan-title">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line p-5">
                <div><h2 class="font-display text-lg font-bold" id="activity-plan-title">Activity plan</h2><p class="mt-1 text-xs text-slate-500">Three stages. One view of delivery.</p></div>
                @if($record->exists)<a class="rounded-lg bg-teal/10 px-3 py-2 text-xs font-semibold text-teal hover:bg-teal/15" href="{{ route('activities.create', ['project_id' => $record->id]) }}">+ Add activity</a>@endif
            </div>
            <nav class="flex flex-wrap gap-2 border-b border-line bg-slate-50/60 px-5 py-3" aria-label="Jump to project stage">
                @foreach($groups as $group)<a class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-teal hover:text-teal" href="#stage-{{ $group['number'] }}">Stage {{ $group['number'] }} <span class="ml-1 text-slate-400">{{ $group['activities']->count() }}</span></a>@endforeach
            </nav>
            <div class="overflow-x-auto" tabindex="0" aria-label="Project activities table, scroll horizontally on small screens">
                <table class="tracking-table w-full text-left">
                    <caption class="sr-only">Project activities grouped by stage, with dates, responsibility, status and remarks</caption>
                    <thead><tr><th scope="col">Activity</th><th scope="col">Start / deadline</th><th scope="col">Responsibility</th><th scope="col">Status</th><th scope="col">Remarks</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                    @foreach($groups as $group)
                        <tbody id="stage-{{ $group['number'] }}" class="scroll-mt-28">
                            <tr class="tracking-stage"><th colspan="6" scope="rowgroup"><div class="flex items-center justify-between gap-4"><div class="flex items-center gap-3"><span class="grid size-8 place-items-center rounded-lg bg-white/15 text-sm">{{ str_pad($group['number'], 2, '0', STR_PAD_LEFT) }}</span><span>Stage {{ $group['number'] }} <span class="mx-1 font-normal text-teal-100/50">/</span> {{ $group['name'] }} <span class="ml-2 text-[11px] font-normal text-teal-100">{{ $group['activities']->count() }} activities</span></span></div><a class="shrink-0 rounded-md bg-white/10 px-2.5 py-1.5 text-[11px] font-medium hover:bg-white/20" href="{{ $trackingUrl }}?context=stage:{{ $group['number'] }}#project-comments" aria-label="Discuss Stage {{ $group['number'] }}">Discuss</a></div></th></tr>
                            @forelse($group['activities'] as $activity)
                                <tr>
                                    <th scope="row"><p class="font-semibold text-ink">{{ $activity['name'] }}</p><p class="mt-1 text-[10px] font-normal uppercase tracking-wide text-slate-400">{{ $activity['record']->data['type'] ?? 'Custom' }}</p></th>
                                    <td><p class="text-slate-400">{{ $activity['start'] ?: 'Start not set' }}</p><p class="mt-1 font-medium {{ $activity['health'] === 'Off track' ? 'text-rose-600' : 'text-slate-700' }}">{{ $activity['due'] ?: 'No deadline' }}</p></td>
                                    <td>{{ $activity['officer'] ?: 'Unassigned' }}</td>
                                    <td><span class="tracking-status" data-health="{{ $activity['health'] }}">{{ $activity['health'] }}</span><p class="mt-1.5 text-[10px] text-slate-400">{{ $activity['status'] }}</p></td>
                                    <td class="tracking-remarks">@if($activity['remarks'])<details><summary class="cursor-pointer text-teal">Read remarks</summary><p class="mt-2 whitespace-pre-wrap break-words">{{ $activity['remarks'] }}</p></details>@else<span class="text-slate-400">No remarks yet</span>@endif</td>
                                    <td><div class="flex flex-col items-start gap-2">@if($activity['record']->exists)<a class="whitespace-nowrap text-xs font-semibold text-teal hover:underline" href="{{ route('activities.edit', $activity['record']) }}">Update activity</a>@endif<a class="text-xs text-slate-500 hover:text-teal hover:underline" href="{{ $trackingUrl }}?context=activity:{{ $activity['key'] }}#project-comments" aria-label="Discuss {{ $activity['name'] }} in Stage {{ $group['number'] }}">Discuss</a></div></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-slate-400">No activities in this stage yet.</td></tr>
                            @endforelse
                        </tbody>
                    @endforeach
                </table>
            </div>
            <div class="border-t border-line bg-slate-50/50 px-5 py-3 text-xs text-slate-500">{{ $summary['unscheduled'] }} activities have no deadline. Completion excludes activities marked Not Applicable.</div>
        </section>
    </div>
    @include('projects.partials.comments')
</div>
@endsection

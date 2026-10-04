@extends('layouts.app')
@section('content')
<section class="portfolio-page space-y-5">
    <a class="text-sm font-semibold text-teal" href="{{ route('projects.index') }}">← All projects</a>
    <header class="project-hero"><div class="flex flex-wrap items-start justify-between gap-4"><div><p class="mb-2 text-xs text-teal-100">{{ $record->data['reference'] ?? 'Project profile' }}{{ $row['demo'] ? ' · Demo project' : '' }}</p><h1 class="font-display text-2xl font-extrabold">{{ $record->data['name'] }}</h1><p class="mt-3 text-sm text-teal-100">{{ $record->data['agency'] ?? 'Implementing agency not assigned' }}</p></div><span class="rounded-full bg-white/15 px-3 py-2 text-xs">Stage {{ $row['phase'] === 'Pipeline' ? '1 · Pipeline' : '2 · Ongoing' }}</span></div></header>
    <nav class="project-view-nav" data-phase="{{ $row['phase'] }}" aria-label="Project information">
        @foreach(['general' => ['General information', 'Project profile & documents'], 'financial' => ['Financial information', 'Yearly & quarterly performance'], ($row['phase'] === 'Pipeline' ? 'pipeline' : 'physical') => ($row['phase'] === 'Pipeline' ? ['Pipeline components', 'Stage 1: 5 components'] : ['Physical progress', 'Stage 2: 6 components & sub-components'])] as $key => [$title, $subtitle])
            @continue($row['phase'] === 'Pipeline' && $key === 'financial')
            <a data-project-view="{{ $key }}" href="{{ $row['url'] }}?tab={{ $key }}" @if($tab === $key) aria-current="page" @endif><strong>{{ $title }}</strong><span>{{ $subtitle }}</span></a>@endforeach
    </nav>
    @if($row['has_issues'])<aside class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><strong>Project requires attention</strong><ul class="mt-2 list-inside list-disc">@foreach($row['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul></aside>@endif
    @if($tab === 'general')
        <div class="grid gap-5 lg:grid-cols-[1.5fr_1fr]">
            <section class="panel p-6"><h2 class="font-display text-lg font-bold">General information</h2><p class="mt-4 text-sm leading-7 text-slate-600">{{ $record->data['description'] ?? 'Project description has not been provided.' }}</p><dl class="project-info-grid mt-6">
                <div><dt>Project cost</dt><dd>{{ isset($record->data['cost']) ? number_format((float) $record->data['cost'], 2) : 'Not provided' }} {{ $record->data['currency'] ?? '' }}</dd></div>
            @foreach(['reference' => 'Project ID / code', 'agency' => 'Implementing agency', 'funding_type' => 'Funding type', 'status' => 'Project status', 'approval_date' => 'Approval date', 'start' => 'Start date', 'completion' => 'End date', 'officer' => 'Project director / focal person', 'designation' => 'Designation', 'email' => 'Email', 'phone' => 'Phone number', 'office' => 'Office / department'] as $key => $label)<div><dt>{{ $label }}</dt><dd>{{ $record->data[$key] ?? 'Not provided' }}</dd></div>@endforeach
            @foreach(['partners' => 'Development partners', 'sectors' => 'Sectors', 'districts' => 'Districts'] as $key => $label)<div><dt>{{ $label }}</dt><dd>{{ implode(', ', (array) ($record->data[$key] ?? [])) ?: 'Not provided' }}</dd></div>@endforeach
            </dl></section>
            <div class="space-y-5"><section class="panel p-6"><p class="eyebrow">Approved project cost</p><p class="mt-4 font-display text-2xl font-extrabold">{{ isset($record->data['cost']) ? number_format((float) $record->data['cost'], 2) : 'Not provided' }}</p><p class="mt-2 text-sm text-slate-500">{{ $record->data['currency'] ?? 'Currency not provided' }}</p></section>
            <section class="panel p-6"><h2 class="font-display text-base font-bold">Project actions</h2>
                <div class="mt-4 flex items-center justify-between gap-3"><h3 class="text-sm font-semibold">Unresolved issues</h3><span class="portfolio-pill">{{ $openIssues->count() }}</span></div>
                <div class="mt-3 space-y-3 project-issues-list {{ $openIssues->count() > 2 ? 'has-overflow' : '' }}">
                    @forelse($openIssues as $issue)
                        <div class="rounded-lg border border-rose-100 bg-rose-50 p-3">
                            @if(isset($issue['url']))<a class="text-sm font-semibold text-rose-800" href="{{ $issue['url'] }}">{{ $issue['name'] }} &rarr;</a>@else<strong class="text-sm text-rose-800">{{ $issue['name'] }}</strong>@endif
                            <p class="mt-1 text-xs text-rose-700">{{ $issue['status'] ?? 'Open' }} &middot; {{ $issue['priority'] ?? 'Normal' }} priority{{ $issue['demo'] ? ' / Demo' : '' }}</p>
                            <p class="mt-2 text-xs text-slate-600">{{ $issue['description'] ?? '' }}</p><p class="mt-2 text-xs text-slate-500">{{ $issue['officer'] ?? 'Unassigned' }}</p>
                        </div>
                    @empty<p class="py-3 text-sm text-slate-500">No unresolved issues for this project.</p>@endforelse
                </div>
                <div class="mt-4 border-t border-slate-100 pt-4"><a class="portfolio-primary" href="{{ route('issues.create', ['project' => $record->data['name']]) }}">+ Add issue</a></div>
                <details class="mt-4"><summary class="cursor-pointer rounded-lg border border-teal p-3 text-center text-sm font-semibold text-teal">View all project issues ({{ $projectIssues->count() }})</summary>
                    <p class="mt-3 text-xs text-slate-500">Includes open and resolved issues.</p>
                    <div class="mt-3 space-y-3 project-issues-list {{ $projectIssues->count() > 2 ? 'has-overflow' : '' }}">
                    @forelse($projectIssues as $issue)
                        <article class="rounded-lg border border-slate-200 p-3">
                            @if(isset($issue['url']))<a class="text-sm font-semibold text-teal" href="{{ $issue['url'] }}">{{ $issue['name'] }} &rarr;</a>@else<strong class="text-sm">{{ $issue['name'] }}</strong>@endif
                            <p class="mt-1 text-xs text-slate-500">{{ $issue['status'] ?? 'Open' }} &middot; {{ $issue['officer'] ?? 'Unassigned' }}{{ $issue['demo'] ? ' / Demo' : '' }}</p>
                            <p class="mt-2 text-xs text-slate-600">{{ $issue['resolution'] ?? $issue['description'] ?? '' }}</p>
                        </article>
                    @empty<p class="text-sm text-slate-500">No issues have been added to this project.</p>@endforelse
                    </div>
                </details>
                @if($record->exists)<div class="mt-4 flex flex-col gap-3"><a class="text-sm font-semibold text-teal" href="{{ route('projects.edit', $record) }}">Edit general information &rarr;</a><a class="text-sm font-semibold text-teal" href="{{ route('projects.show', $record) }}">Activity tracking &amp; project chat &rarr;</a></div>@endif
            </section>
            <section class="panel p-6"><h2 class="font-display text-base font-bold">Related documents</h2><div class="mt-4 space-y-3">@forelse($record->data['attachments'] ?? [] as $index => $document)<a class="block text-sm font-semibold text-teal" href="{{ route('projects.documents.download', [$record, $index]) }}">{{ $document['title'] }} ↗</a>@empty<p class="text-sm text-slate-500">No documents attached yet.</p>@endforelse</div></section></div>
        </div>
    @elseif($tab === 'financial')
        @include('projects.partials.financial')
    @else
        @include('projects.partials.components')
    @endif
</section>
<div data-demo-project-chat data-project-key="{{ $record->data['reference'] ?? $record->id }}" data-project-officer="{{ $record->data['officer'] ?? 'Project Director' }}">
    <button type="button" class="detail-chat-launcher" data-chat-launch aria-label="Open project comments" aria-haspopup="dialog"><svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-3 3V11.5a10 10 0 0 1 20 0Z"/><path d="M6 10h10M6 14h7"/></svg><span data-chat-count class="detail-chat-count">2</span></button>
    <dialog class="detail-chat-dialog" aria-labelledby="detail-chat-title">
        <header class="flex items-start justify-between gap-3 border-b border-slate-100 p-5"><div><h2 id="detail-chat-title" class="font-display text-base font-bold">Project comments</h2><p class="mt-1 text-xs text-slate-500">{{ $record->data['name'] }}</p><p class="mt-2 text-xs text-teal">Demo chat &middot; Saved in this browser only</p></div><button type="button" data-chat-close aria-label="Close comments" class="p-2 text-xl">&times;</button></header>
        <div class="detail-chat-messages" data-chat-messages aria-live="polite"></div>
        <form data-chat-form class="border-t border-slate-100 p-4"><div data-chat-reply-context class="mb-2 flex items-center justify-between text-xs text-teal" hidden><span></span><button type="button" data-chat-cancel>Cancel reply</button></div><label class="text-xs font-semibold text-slate-500" for="detail-chat-body">Your comment or reply</label><textarea id="detail-chat-body" class="field mt-2" rows="3" maxlength="2000" required placeholder="Write a comment..."></textarea><div class="mt-3 flex items-center justify-between gap-3"><span class="text-xs text-slate-400">Posting as Demo user</span><button class="portfolio-primary">Send</button></div></form>
    </dialog>
</div>
@endsection

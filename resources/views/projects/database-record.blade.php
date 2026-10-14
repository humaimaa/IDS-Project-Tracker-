@extends('layouts.app')
@section('content')
<section class="portfolio-page space-y-5">
@if($record instanceof \App\Models\Meeting && $record->minutes_path)<a class="portfolio-primary" href="{{ route('meetings.minutes', $record->id) }}">Download minutes document</a>@endif
<a href="{{ route($module.'.index') }}" class="text-sm text-teal">&larr; {{ $definition['title'] }}</a>
<section class="panel p-6"><div class="flex flex-wrap justify-between gap-4"><h1 class="font-display text-xl font-bold">{{ $record->name ?? $record->title ?? $definition['title'].' #'.$record->id }}</h1>@if(!($definition['append_only'] ?? false) && (!($record instanceof \App\Models\FinancialReport) || (!$record->project->archived_at)) && auth()->user()?->hasPortfolioPermission($module.'.update') && ($module !== 'roles' || (auth()->user()->role_id !== $record->id && auth()->user()->canGrantRole($record->id))) && (!($record instanceof \App\Models\User) || !$record->role_id || auth()->user()->canGrantRole($record->role_id)))<a class="portfolio-primary" href="{{ route($module === 'financial-reports' ? 'financial-information.edit' : $module.'.edit', $record->id) }}">Edit</a>@endif</div>
<dl class="project-info-grid mt-6">@foreach($definition['fields'] as $field => $rule)@continue($field === 'password')<div><dt>{{ ucwords(str_replace('_', ' ', $field)) }}</dt><dd class="whitespace-pre-line">{{ $options[$field][(string) $record->getRawOriginal($field)] ?? ($record->$field instanceof \DateTimeInterface ? $record->$field->format('d M Y H:i') : ($record->$field ?? 'Not supplied')) }}</dd></div>@endforeach</dl>
@if($record instanceof \App\Models\Meeting && auth()->user()->hasPortfolioPermission('projects.view'))<div class="mt-6"><h2 class="font-bold">Related projects</h2>@forelse($record->projects as $project)<a class="mt-2 block text-teal" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>@empty<p class="mt-2 text-sm text-slate-500">No linked projects.</p>@endforelse</div>@endif
@if($record->project_id && auth()->user()->hasPortfolioPermission('projects.view'))<a class="mt-6 inline-block text-teal" href="{{ route('projects.show', $record->project_id) }}">View project &rarr;</a>@endif
</section>
</section>
@endsection

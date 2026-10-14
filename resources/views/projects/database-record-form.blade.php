@extends('layouts.app')
@section('content')
<form method="post" enctype="multipart/form-data" action="{{ $record->exists ? route($module.'.update', $record->id) : route($module.'.store') }}" class="panel mx-auto max-w-4xl space-y-5 p-6">
@csrf @if($record->exists) @method('PUT') @endif
<h1 class="font-display text-xl font-bold">{{ $record->exists ? 'Edit' : 'Add' }} · {{ $definition['title'] }}</h1>
@if($errors->any())<div class="rounded-lg bg-rose-50 p-4 text-sm text-rose-800" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if(str_contains($module, 'financial') || $module === 'project-costs')<p class="text-sm text-slate-500">Enter amounts in whole currency units, not millions. Leave unknown amounts blank.</p>@endif
@if($module === 'pipeline-steps' && $record->exists && $record->planned_date)<p class="text-sm text-slate-500">To change the planned deadline, request an extension from the project's Pipeline steps tab.</p>@endif
@if($module === 'issues')@include('projects.partials.issue-target-fields')@endif
<div class="grid gap-5 sm:grid-cols-2">@foreach($definition['fields'] as $field => $rule)
@continue($module === 'issues' && in_array($field, ['project_id', 'component_id', 'pipeline_step_id', 'subcomponent_id']))
@php($value = old($field, $field === 'password' ? '' : ($record->$field instanceof \DateTimeInterface ? $record->$field->format($field === 'scheduled_at' ? 'Y-m-d\TH:i' : 'Y-m-d') : ($record->getRawOriginal($field) ?? request($field)))))
<label class="block text-sm font-semibold text-slate-600">{{ ucwords(str_replace('_', ' ', $field)) }}
@if(isset($options[$field]))<select class="field mt-2" name="{{ $field }}" @required(str_contains($rule, 'required'))><option value="">Select...</option>@foreach($options[$field] as $key => $label)<option value="{{ $key }}" @selected((string) $value === (string) $key)>{{ $label }}</option>@endforeach</select>
@elseif(in_array($field, ['description', 'remarks', 'resolution', 'agenda', 'minutes', 'source_notes', 'terms']))<textarea class="field mt-2 !h-auto" rows="4" name="{{ $field }}">{{ $value }}</textarea>
@else<input class="field mt-2" name="{{ $field }}" type="{{ $field === 'password' ? 'password' : ($field === 'email' ? 'email' : (str_contains($rule, 'date') ? ($field === 'scheduled_at' ? 'datetime-local' : 'date') : ((str_contains($rule, 'numeric') || str_contains($rule, 'integer')) ? 'number' : 'text'))) }}" @if(str_contains($rule, 'numeric')) step="0.01" @endif value="{{ $value }}" @required(str_contains($rule, 'required')) @readonly(($field === 'baseline_end' && $record->exists && $record->baseline_end) || ($module === 'pipeline-steps' && $field === 'planned_date' && $record->exists && $record->planned_date))>
@endif</label>@endforeach</div>
@if($module === 'meetings')<label class="block text-sm">Related projects<select multiple name="projects[]" class="field mt-2 !h-32">@foreach($projects as $project)<option value="{{ $project->id }}" @selected(in_array($project->id, old('projects', $record->exists ? $record->projects->pluck('id')->all() : [])))>{{ $project->name }}</option>@endforeach</select></label>@endif
@if($module === 'meetings')
<label class="block text-sm font-semibold text-slate-600">Minutes document
    <input type="file" name="minutes_document" accept=".pdf,.doc,.docx" class="field mt-2 !h-auto py-3">
    <span class="mt-2 block text-xs font-normal text-slate-500">For held meetings only. PDF or Word document, up to 20 MB. Leave empty to keep the current document.</span>
</label>
@if($record->minutes_path && auth()->user()->hasPortfolioPermission('meetings.view'))<a class="text-sm text-teal" href="{{ route('meetings.minutes', $record->id) }}">Download current minutes</a>@endif
@endif
@if($module === 'roles')<fieldset><legend class="mb-3 font-semibold">Permissions</legend><div class="grid gap-3 sm:grid-cols-2">@foreach($permissions as $permission)<label class="text-sm"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, old('permissions', $selectedPermissions)))> {{ ucwords(str_replace(['.', '-'], [' — ', ' '], $permission->code)) }}</label>@endforeach</div></fieldset>@endif
<div class="flex gap-4"><button class="portfolio-primary">Save</button>@if(auth()->user()->hasPortfolioPermission($module.'.view'))<a class="py-3 text-sm text-teal" href="{{ route($module.'.index') }}">Cancel</a>@endif</div>
</form>
@endsection

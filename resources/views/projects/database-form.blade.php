@extends('layouts.app')
@section('content')
<form data-project-creation class="panel mx-auto max-w-5xl space-y-6 p-6" method="post" action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}">
@csrf @if($project->exists) @method('PUT') @endif
<h1 class="font-display text-2xl font-extrabold">{{ $project->exists ? 'Edit project' : 'Add project' }}</h1>
@if($errors->any())<div class="rounded-lg bg-rose-50 p-4 text-sm text-rose-800" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="grid gap-5 sm:grid-cols-2">
@foreach(['reference_code' => 'Project / ADP code', 'name' => 'Project name', 'start_date' => 'Start date', 'current_end_date' => 'Current closing date', 'approval_forum' => 'Approval forum', 'approval_date' => 'Approval date'] as $field => $label)<label class="block text-sm font-semibold text-slate-600">{{ $label }}<input class="field mt-2" name="{{ $field }}" value="{{ old($field, $project->$field instanceof \DateTimeInterface ? $project->$field->format('Y-m-d') : $project->$field) }}" type="{{ str_ends_with($field, '_date') ? 'date' : 'text' }}" @required(in_array($field, ['name', 'reference_code']))></label>@endforeach
@foreach(['funding_type' => ['Grant', 'Loan', 'Govt. share', 'Mixed'], 'phase' => ['Pipeline', 'Ongoing'], 'lifecycle' => ['Active', 'Completed', 'Cancelled']] as $field => $choices)<label class="text-sm font-semibold text-slate-600">{{ ucwords(str_replace('_', ' ', $field)) }}<select class="field mt-2" name="{{ $field }}">@if($field === 'funding_type')<option value="">Select funding type</option>@endif @foreach($choices as $choice)<option @selected(old($field, $field === 'funding_type' && str_starts_with($project->funding_type ?? '', 'Loan') ? 'Loan' : $project->$field) === $choice)>{{ $choice }}</option>@endforeach</select></label>@endforeach
@foreach(['sector_id' => $sectors] as $field => $choices)<label class="text-sm font-semibold text-slate-600">{{ $field === 'sector_id' ? 'Sector' : 'District' }}<select class="field mt-2" name="{{ $field }}" required><option value="">Select one...</option>@foreach($choices as $choice)<option value="{{ $choice->id }}" @selected((string) old($field, $project->$field) === (string) $choice->id)>{{ $choice->name }}</option>@endforeach</select></label>@endforeach
@include('projects.partials.multi-select', ['field' => 'district_ids', 'label' => 'Districts', 'choices' => $districts, 'selected' => old('district_ids', $project->districts->isNotEmpty() ? $project->districts->pluck('id')->all() : array_filter([$project->district_id]))])
<label class="text-sm font-semibold text-slate-600">Implementing agency<input class="field mt-2" name="implementing_agency" maxlength="255" value="{{ old('implementing_agency', $project->implementing_agency ?? $project->organizations->firstWhere('pivot.role', 'implementing')?->name) }}" placeholder="Enter implementing agency"></label>
@include('projects.partials.multi-select', ['field' => 'donors', 'label' => 'Development partners / donors', 'choices' => $organizations, 'selected' => old('donors', $project->organizations->where('pivot.role', 'donor')->pluck('id')->all())])
</div>
<label class="block text-sm font-semibold text-slate-600">Objective<textarea class="field mt-2 !h-auto" rows="4" name="objective">{{ old('objective', $project->objective) }}</textarea></label>
@if(auth()->user()->hasPortfolioPermission('projects.flagship'))<label class="block text-sm"><input type="checkbox" name="is_flagship" value="1" @checked(old('is_flagship', $project->is_flagship))> Flagship project</label>@endif
@if($project->exists)<label class="block text-sm font-semibold text-slate-600">Reason for phase or closing-date change<textarea class="field mt-2 !h-auto" rows="2" name="change_reason">{{ old('change_reason') }}</textarea></label>@endif
@if(auth()->user()?->hasPortfolioPermission('projects.assign'))<fieldset class="grid gap-5 rounded-xl bg-teal-50 p-5 sm:grid-cols-2"><legend class="font-bold">Project access and extension approval</legend><label class="text-sm sm:col-span-2">Project director<select name="project_director_id" class="field mt-2"><option value="">Not assigned</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string) old('project_director_id', $project->project_director_id) === (string) $user->id)>{{ $user->name }}</option>@endforeach</select></label>@include('projects.partials.multi-select', ['field' => 'members', 'label' => 'Project team', 'choices' => $users, 'selected' => old('members', $assignments->pluck('user_id')->all())])
@include('projects.partials.multi-select', ['field' => 'approvers', 'label' => 'Extension approvers', 'choices' => $extensionApprovers, 'selected' => old('approvers', $assignments->where('can_approve_extension', true)->pluck('user_id')->all())])
<p class="text-xs text-slate-500 sm:col-span-2">Only users with extension approval permission are listed. The project director cannot approve their own project extensions.</p></fieldset>@endif
@if(!$project->exists && auth()->user()->hasPortfolioPermission('project-costs.create'))
<fieldset class="grid gap-4 rounded-xl bg-teal-50 p-5 sm:grid-cols-2"><legend class="font-bold">Project cost (optional)</legend><label class="text-sm">Total cost in whole currency units<input type="number" min="0" step="0.01" name="initial_cost" value="{{ old('initial_cost') }}" class="field mt-2"></label><label class="text-sm">Currency<select name="cost_currency" class="field mt-2"><option value="">Select currency</option>@foreach($currencies as $currency)<option value="{{ $currency->code }}" @selected(old('cost_currency') === $currency->code)>{{ $currency->code }} — {{ $currency->name }}</option>@endforeach</select></label></fieldset>
@endif
<section data-pipeline-creation class="space-y-4 rounded-xl border border-teal-100 bg-teal-50 p-5">
    <h2 class="font-display text-lg font-bold">Pipeline components</h2>
    <p class="text-sm text-slate-600">Choose predefined names in order. Each component can be used once. Set its dates, responsible officer and details below.</p>
    @if(auth()->user()->hasPortfolioPermission('pipeline-steps.update') || auth()->user()->hasPortfolioPermission('pipeline-steps.create'))
    @if(!$project->exists || auth()->user()->hasPortfolioPermission('pipeline-steps.update'))<label class="block text-sm font-semibold">Current component<select class="field mt-2" name="pipeline_template_id">@foreach($pipelineTemplates as $template)<option value="{{ $template->id }}" @selected((string) old('pipeline_template_id', $project->pipelineSteps->firstWhere('is_current', true)?->template_id ?? $pipelineTemplates->first()?->id) === (string) $template->id)>{{ $template->name }}</option>@endforeach</select></label>@endif
    @php($pipelineRows = old('pipeline_components', $project->pipelineSteps->isNotEmpty() ? $project->pipelineSteps->map(fn ($step) => ['template_id' => $step->template_id, 'start_date' => $step->start_date?->toDateString(), 'planned_date' => $step->planned_date?->toDateString(), 'responsible_office' => $step->responsible_office, 'remarks' => $step->remarks])->all() : $pipelineTemplates->take(1)->map(fn ($template) => ['template_id' => $template->id])->all()))
    <div data-pipeline-list class="space-y-4">
    @foreach($pipelineRows as $index => $step)
    @continue(!auth()->user()->hasPortfolioPermission('pipeline-steps.'.($project->pipelineSteps->contains('template_id', $step['template_id'] ?? null) ? 'update' : 'create')))
    @include('projects.partials.create-pipeline-component')
    @endforeach
    </div>
    @if(auth()->user()->hasPortfolioPermission('pipeline-steps.create'))<button type="button" data-add-pipeline class="portfolio-primary">+ Add Component</button>
    <template id="new-pipeline-component">@include('projects.partials.create-pipeline-component', ['index' => '__PIPELINE__', 'step' => ['template_id' => 0]])</template>@endif
    @else<p class="text-sm text-slate-500">Your role cannot edit pipeline component details.</p>@endif
</section>
<section data-ongoing-creation class="space-y-4 rounded-xl border border-teal-100 bg-teal-50 p-5">
    <h2 class="font-display text-lg font-bold">Ongoing project components</h2><p class="text-sm text-slate-600">Add custom components. Add subcomponents only where needed. All items are saved with this project.</p>
    @if(auth()->user()->hasPortfolioPermission('activities.create') || ($project->exists && auth()->user()->hasPortfolioPermission('subcomponents.create')))
    <div data-component-list class="space-y-4">@foreach(old('components', $project->exists ? $project->components->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])->all() : []) as $index => $component)@include('projects.partials.create-component')@endforeach</div>
    @if(auth()->user()->hasPortfolioPermission('activities.create'))<button type="button" data-add-component class="portfolio-primary">+ Add component</button>@endif
    @else<p class="text-sm text-slate-500">Your role can create the project. An authorized user can add its components later.</p>@endif
</section>
<template id="new-project-component">@include('projects.partials.create-component', ['index' => '__COMPONENT__', 'component' => []])</template>
<template id="new-project-subcomponent">@include('projects.partials.create-subcomponent', ['index' => '__COMPONENT__', 'subIndex' => '__SUB__', 'subcomponent' => []])</template>
<div class="flex gap-4"><button class="portfolio-primary">Save project</button>@if(auth()->user()->hasPortfolioPermission('projects.view'))<a href="{{ route('projects.index') }}" class="py-3 text-sm text-teal">Cancel</a>@endif</div>
</form>
@endsection

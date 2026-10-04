<form class="panel space-y-6 p-5 sm:p-6" method="post" enctype="multipart/form-data" action="{{ $record?->exists ? route($module.'.update', $record) : route($module.'.store') }}">
    @csrf
    @if($record?->exists) @method('PUT') @endif
    @if($errors->any())
        <div class="rounded-lg bg-rose-50 p-4 text-sm text-rose-700" role="alert"><p class="font-semibold">Please correct the following:</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <x-tracker-field name="name" label="Issue title" type="text" :required="true" :value="$record?->data['name'] ?? null" :options="$fieldOptions['name'] ?? config('tracker.issues.fields.0.4', [])" />
        <x-tracker-field name="description" label="Issue description" type="textarea" :required="false" :value="$record?->data['description'] ?? null" :options="$fieldOptions['description'] ?? config('tracker.issues.fields.1.4', [])" />
        <x-tracker-field name="project" label="Project" type="select" :required="true" :value="$record?->data['project'] ?? request('project')" :options="$fieldOptions['project'] ?? config('tracker.issues.fields.2.4', [])" />
        <x-tracker-field name="stage" label="Affected stage" type="select" :required="true" :value="$record?->data['stage'] ?? null" :options="$fieldOptions['stage'] ?? config('tracker.issues.fields.3.4', [])" />
        <x-tracker-field name="activity" label="Affected activity" type="text" :required="false" :value="$record?->data['activity'] ?? null" :options="$fieldOptions['activity'] ?? config('tracker.issues.fields.4.4', [])" />
        <x-tracker-field name="subactivity" label="Affected sub-activity" type="text" :required="false" :value="$record?->data['subactivity'] ?? null" :options="$fieldOptions['subactivity'] ?? config('tracker.issues.fields.5.4', [])" />
        <x-tracker-field name="reported_at" label="Date reported" type="date" :required="true" :value="$record?->data['reported_at'] ?? null" :options="$fieldOptions['reported_at'] ?? config('tracker.issues.fields.6.4', [])" />
        <x-tracker-field name="reported_by" label="Reported by" type="text" :required="true" :value="$record?->data['reported_by'] ?? null" :options="$fieldOptions['reported_by'] ?? config('tracker.issues.fields.7.4', [])" />
        <x-tracker-field name="priority" label="Priority" type="select" :required="true" :value="$record?->data['priority'] ?? null" :options="$fieldOptions['priority'] ?? config('tracker.issues.fields.8.4', [])" />
        <x-tracker-field name="officer" label="Responsible officer or agency" type="text" :required="true" :value="$record?->data['officer'] ?? null" :options="$fieldOptions['officer'] ?? config('tracker.issues.fields.9.4', [])" />
        <x-tracker-field name="action" label="Required action" type="textarea" :required="false" :value="$record?->data['action'] ?? null" :options="$fieldOptions['action'] ?? config('tracker.issues.fields.10.4', [])" />
        <x-tracker-field name="due" label="Target resolution date" type="date" :required="false" :value="$record?->data['due'] ?? null" :options="$fieldOptions['due'] ?? config('tracker.issues.fields.11.4', [])" />
        <x-tracker-field name="status" label="Status" type="select" :required="true" :value="$record?->data['status'] ?? null" :options="$fieldOptions['status'] ?? config('tracker.issues.fields.12.4', [])" />
        <x-tracker-field name="follow_up" label="Follow-up remarks" type="textarea" :required="false" :value="$record?->data['follow_up'] ?? null" :options="$fieldOptions['follow_up'] ?? config('tracker.issues.fields.13.4', [])" />
        <x-tracker-field name="resolution" label="Resolution details" type="textarea" :required="false" :value="$record?->data['resolution'] ?? null" :options="$fieldOptions['resolution'] ?? config('tracker.issues.fields.14.4', [])" />
    </div>

    @include('partials.documents-form')
    <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
        <a class="rounded-lg border border-slate-200 px-4 py-2 text-sm" href="{{ $record?->exists ? route($module.'.show', $record) : route($module.'.index') }}">Cancel</a>
        <button class="rounded-lg bg-teal px-5 py-2 text-sm font-semibold text-white">Save {{ strtolower($definition['singular']) }}</button>
    </div>
</form>

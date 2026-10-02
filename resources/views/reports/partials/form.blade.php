<form class="panel space-y-6 p-5 sm:p-6" method="post" action="{{ $record?->exists ? route($module.'.update', $record) : route($module.'.store') }}">
    @csrf
    @if($record?->exists) @method('PUT') @endif
    @if($errors->any())
        <div class="rounded-lg bg-rose-50 p-4 text-sm text-rose-700" role="alert"><p class="font-semibold">Please correct the following:</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <x-tracker-field name="name" label="Report name" type="text" :required="true" :value="$record?->data['name'] ?? null" :options="$fieldOptions['name'] ?? config('tracker.reports.fields.0.4', [])" />
        <x-tracker-field name="type" label="Report type" type="select" :required="true" :value="$record?->data['type'] ?? null" :options="$fieldOptions['type'] ?? config('tracker.reports.fields.1.4', [])" />
        <x-tracker-field name="projects" label="Projects" type="multiselect" :required="false" :value="$record?->data['projects'] ?? null" :options="$fieldOptions['projects'] ?? config('tracker.reports.fields.2.4', [])" />
        <x-tracker-field name="period_start" label="Reporting period start" type="date" :required="false" :value="$record?->data['period_start'] ?? null" :options="$fieldOptions['period_start'] ?? config('tracker.reports.fields.3.4', [])" />
        <x-tracker-field name="period_end" label="Reporting period end" type="date" :required="false" :value="$record?->data['period_end'] ?? null" :options="$fieldOptions['period_end'] ?? config('tracker.reports.fields.4.4', [])" />
        <x-tracker-field name="status" label="Format" type="select" :required="true" :value="$record?->data['status'] ?? null" :options="$fieldOptions['status'] ?? config('tracker.reports.fields.5.4', [])" />
    </div>

    <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
        <a class="rounded-lg border border-slate-200 px-4 py-2 text-sm" href="{{ $record?->exists ? route($module.'.show', $record) : route($module.'.index') }}">Cancel</a>
        <button class="rounded-lg bg-teal px-5 py-2 text-sm font-semibold text-white">Save {{ strtolower($definition['singular']) }}</button>
    </div>
</form>

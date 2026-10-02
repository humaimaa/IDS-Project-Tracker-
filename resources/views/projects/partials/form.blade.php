<form class="panel space-y-6 p-5 sm:p-6" method="post" enctype="multipart/form-data" action="{{ $record?->exists ? route($module.'.update', $record) : route($module.'.store') }}">
    @csrf
    @if($record?->exists) @method('PUT') @endif
    @if($errors->any())
        <div class="rounded-lg bg-rose-50 p-4 text-sm text-rose-700" role="alert"><p class="font-semibold">Please correct the following:</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <x-tracker-field name="name" label="Project name" type="text" :required="true" :value="$record?->data['name'] ?? null" :options="$fieldOptions['name'] ?? config('tracker.projects.fields.0.4', [])" />
        <x-tracker-field name="reference" label="Reference number" type="text" :required="true" :value="$record?->data['reference'] ?? null" :options="$fieldOptions['reference'] ?? config('tracker.projects.fields.1.4', [])" />
        <x-tracker-field name="stage" label="Current stage" type="select" :required="true" :value="$record?->data['stage'] ?? null" :options="$fieldOptions['stage'] ?? config('tracker.projects.fields.2.4', [])" />
        <x-tracker-field name="status" label="Project status" type="select" :required="true" :value="$record?->data['status'] ?? null" :options="$fieldOptions['status'] ?? config('tracker.projects.fields.3.4', [])" />
        <x-tracker-field name="description" label="Brief description" type="textarea" :required="false" :value="$record?->data['description'] ?? null" :options="$fieldOptions['description'] ?? config('tracker.projects.fields.4.4', [])" />
        <x-tracker-field name="cost" label="Project cost" type="number" :required="false" :value="$record?->data['cost'] ?? null" :options="$fieldOptions['cost'] ?? config('tracker.projects.fields.5.4', [])" />
        <x-tracker-field name="currency" label="Currency" type="text" :required="false" :value="$record?->data['currency'] ?? null" :options="$fieldOptions['currency'] ?? config('tracker.projects.fields.6.4', [])" />
        <x-tracker-field name="agency" label="Implementing agency" type="select" :required="true" :value="$record?->data['agency'] ?? null" :options="$fieldOptions['agency'] ?? config('tracker.projects.fields.7.4', [])" />
        <x-tracker-field name="districts" label="Districts" type="multiselect" :required="false" :value="$record?->data['districts'] ?? null" :options="$fieldOptions['districts'] ?? config('tracker.projects.fields.8.4', [])" />
        <x-tracker-field name="sectors" label="Sectors" type="multiselect" :required="false" :value="$record?->data['sectors'] ?? null" :options="$fieldOptions['sectors'] ?? config('tracker.projects.fields.9.4', [])" />
        <x-tracker-field name="partners" label="Development partners / donors" type="multiselect" :required="false" :value="$record?->data['partners'] ?? null" :options="$fieldOptions['partners'] ?? config('tracker.projects.fields.10.4', [])" />
        <x-tracker-field name="officer" label="Responsible officer" type="text" :required="false" :value="$record?->data['officer'] ?? null" :options="$fieldOptions['officer'] ?? config('tracker.projects.fields.11.4', [])" />
  </div>
    @include('partials.documents-form')
    <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
        <a class="rounded-lg border border-slate-200 px-4 py-2 text-sm" href="{{ $record?->exists ? route($module.'.show', $record) : route($module.'.index') }}">Cancel</a>
        <button class="rounded-lg bg-teal px-5 py-2 text-sm font-semibold text-white">Save {{ strtolower($definition['singular']) }}</button>
    </div>
</form>

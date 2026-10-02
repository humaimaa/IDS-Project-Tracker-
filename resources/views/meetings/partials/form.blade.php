<form class="panel space-y-6 p-5 sm:p-6" method="post" enctype="multipart/form-data" action="{{ $record?->exists ? route($module.'.update', $record) : route($module.'.store') }}">
    @csrf
    @if($record?->exists) @method('PUT') @endif
    @if($errors->any())
        <div class="rounded-lg bg-rose-50 p-4 text-sm text-rose-700" role="alert"><p class="font-semibold">Please correct the following:</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <x-tracker-field name="name" label="Meeting title" type="text" :required="true" :value="$record?->data['name'] ?? null" :options="$fieldOptions['name'] ?? config('tracker.meetings.fields.0.4', [])" />
        <x-tracker-field name="projects" label="Related projects" type="multiselect" :required="true" :value="$record?->data['projects'] ?? null" :options="$fieldOptions['projects'] ?? config('tracker.meetings.fields.1.4', [])" />
        <x-tracker-field name="date" label="Date and time" type="datetime-local" :required="true" :value="$record?->data['date'] ?? null" :options="$fieldOptions['date'] ?? config('tracker.meetings.fields.2.4', [])" />
        <x-tracker-field name="venue" label="Venue" type="text" :required="false" :value="$record?->data['venue'] ?? null" :options="$fieldOptions['venue'] ?? config('tracker.meetings.fields.3.4', [])" />
        <x-tracker-field name="meeting_link" label="Online meeting link" type="url" :required="false" :value="$record?->data['meeting_link'] ?? null" :options="$fieldOptions['meeting_link'] ?? config('tracker.meetings.fields.4.4', [])" />
        <x-tracker-field name="participants" label="Participants and organizations" type="textarea" :required="false" :value="$record?->data['participants'] ?? null" :options="$fieldOptions['participants'] ?? config('tracker.meetings.fields.5.4', [])" />
        <x-tracker-field name="agenda" label="Agenda" type="textarea" :required="false" :value="$record?->data['agenda'] ?? null" :options="$fieldOptions['agenda'] ?? config('tracker.meetings.fields.6.4', [])" />
        <x-tracker-field name="status" label="Status" type="select" :required="true" :value="$record?->data['status'] ?? null" :options="$fieldOptions['status'] ?? config('tracker.meetings.fields.7.4', [])" />
        <x-tracker-field name="minutes" label="Meeting minutes" type="textarea" :required="false" :value="$record?->data['minutes'] ?? null" :options="$fieldOptions['minutes'] ?? config('tracker.meetings.fields.8.4', [])" />
        <x-tracker-field name="decisions" label="Decisions" type="textarea" :required="false" :value="$record?->data['decisions'] ?? null" :options="$fieldOptions['decisions'] ?? config('tracker.meetings.fields.9.4', [])" />
    </div>

    @include('meetings.partials.actions')
    @include('partials.documents-form')
    <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
        <a class="rounded-lg border border-slate-200 px-4 py-2 text-sm" href="{{ $record?->exists ? route($module.'.show', $record) : route($module.'.index') }}">Cancel</a>
        <button class="rounded-lg bg-teal px-5 py-2 text-sm font-semibold text-white">Save {{ strtolower($definition['singular']) }}</button>
    </div>
</form>

<form class="panel space-y-6 p-5 sm:p-6" method="post" action="{{ $record?->exists ? route($module.'.update', $record) : route($module.'.store') }}">
    @csrf
    @if($record?->exists) @method('PUT') @endif
    @if($errors->any())
        <div class="rounded-lg bg-rose-50 p-4 text-sm text-rose-700" role="alert"><p class="font-semibold">Please correct the following:</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <p class="text-sm text-slate-500">Assign an existing role to this user. Permissions are managed on the separate role form.</p>
    @if(empty($fieldOptions['role']))
        <p class="rounded-lg bg-amber-50 p-4 text-sm">No roles have been created. <a class="font-semibold text-teal" href="{{ route('roles.create') }}">Create a role first</a>, then return to add a user.</p>
    @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <x-tracker-field name="name" label="Name" type="text" :required="true" :value="$record?->data['name'] ?? null" :options="$fieldOptions['name'] ?? config('tracker.users.fields.0.4', [])" />
        <x-tracker-field name="email" label="Email" type="email" :required="true" :value="$record?->data['email'] ?? null" :options="$fieldOptions['email'] ?? config('tracker.users.fields.1.4', [])" />
        <x-tracker-field name="role" label="Role" type="select" :required="true" :value="$record?->data['role'] ?? null" :options="$fieldOptions['role'] ?? config('tracker.users.fields.2.4', [])" />
        <x-tracker-field name="status" label="Account status" type="select" :required="true" :value="$record?->data['status'] ?? null" :options="$fieldOptions['status'] ?? config('tracker.users.fields.3.4', [])" />
        <x-tracker-field name="projects" label="Assigned projects" type="multiselect" :required="false" :value="$record?->data['projects'] ?? null" :options="$fieldOptions['projects'] ?? config('tracker.users.fields.4.4', [])" />
    </div>

    <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
        <a class="rounded-lg border border-slate-200 px-4 py-2 text-sm" href="{{ $record?->exists ? route($module.'.show', $record) : route($module.'.index') }}">Cancel</a>
        <button class="rounded-lg bg-teal px-5 py-2 text-sm font-semibold text-white">Save {{ strtolower($definition['singular']) }}</button>
    </div>
</form>

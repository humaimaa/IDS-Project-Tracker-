<form class="panel space-y-6 p-5 sm:p-6" method="post" action="{{ $record?->exists ? route($module.'.update', $record) : route($module.'.store') }}">
    @csrf
    @if($record?->exists) @method('PUT') @endif
    @if($errors->any())
        <div class="rounded-lg bg-rose-50 p-4 text-sm text-rose-700" role="alert"><p class="font-semibold">Please correct the following:</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <x-tracker-field name="name" label="Role name" :required="true" :value="$record?->data['name'] ?? null" />
        <x-tracker-field name="description" label="Description" type="textarea" :value="$record?->data['description'] ?? null" />
        <fieldset class="sm:col-span-2">
            <legend class="font-semibold">Permissions</legend>
            <p class="mt-1 text-sm text-slate-500">Choose the permissions assigned to users with this role.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($fieldOptions['permissions'] as $permission)
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm">
                        <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, old('permissions', $record?->data['permissions'] ?? [])))>
                        <span>{{ $permission }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    </div>
    <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
        <a class="rounded-lg border border-slate-200 px-4 py-2 text-sm" href="{{ $record?->exists ? route($module.'.show', $record) : route($module.'.index') }}">Cancel</a>
        <button class="rounded-lg bg-teal px-5 py-2 text-sm font-semibold text-white">Save {{ strtolower($definition['singular']) }}</button>
    </div>
</form>

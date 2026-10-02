<form id="work-item-form" class="panel space-y-6 p-5 sm:p-6" method="post" enctype="multipart/form-data" action="{{ $record?->exists ? route($module.'.update', $record) : route($module.'.store') }}">
    @csrf
    @if($record?->exists) @method('PUT') @endif
    @if($errors->any())
        <div class="rounded-lg bg-rose-50 p-4 text-sm text-rose-700" role="alert"><p class="font-semibold">Please correct the following:</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <p class="text-sm text-slate-500">Predefined items use the common stage list. Custom items belong only to the selected project and stage. Progress is saved separately for each project.</p>
    <div class="grid gap-5 sm:grid-cols-2">
        @foreach($definition['fields'] as $field)
            @php([$name, $label, $type, $required] = $field)
            @if(in_array($name, ['project_id', 'parent_id', 'template_id']))
                <label class="block text-sm font-semibold text-slate-600" data-work-field="{{ $name }}">
                    {{ $label }} @if($required)<span class="text-rose-500">*</span>@endif
                    <select class="field mt-2" name="{{ $name }}" @required($required)>
                        <option value="">Choose {{ strtolower($label) }}</option>
                        @if($name === 'project_id')
                            @foreach($projectOptions as $project)
                                <option value="{{ $project->id }}" @selected((string) old($name, $record?->data[$name] ?? request('project_id')) === (string) $project->id)>{{ $project->data['name'] }} ({{ $project->data['reference'] ?? $project->id }})</option>
                            @endforeach
                        @elseif($name === 'parent_id')
                            @foreach($parentOptions as $parent)
                                <option value="{{ $parent->id }}" data-project="{{ $parent->data['project_id'] ?? '' }}" data-stage="{{ $parent->data['stage'] ?? '' }}" data-template="{{ $parent->data['template_id'] ?? '' }}" @selected((string) old($name, $record?->data[$name] ?? request('parent_id')) === (string) $parent->id)>{{ $parent->data['name'] }}</option>
                            @endforeach
                        @else
                            @foreach(config('tracker.projects.activity_templates') as $templateId => $template)
                                @if($module === 'activities')
                                    <option value="{{ $templateId }}" data-stage="{{ $template['stage'] }}" @selected(old($name, $record?->data[$name] ?? '') === $templateId)>{{ $template['name'] }}</option>
                                @else
                                    @foreach($template['subactivities'] as $childId => $childName)
                                        <option value="{{ $childId }}" data-stage="{{ $template['stage'] }}" data-parent-template="{{ $templateId }}" @selected(old($name, $record?->data[$name] ?? '') === $childId)>{{ $childName }}</option>
                                    @endforeach
                                @endif
                            @endforeach
                        @endif
                    </select>
                </label>
            @else
                <div data-work-field="{{ $name }}">
                    <x-tracker-field :name="$name" :label="$label" :type="$type" :required="$required && $name !== 'name'" :value="$record?->data[$name] ?? ($name === 'type' ? 'Custom' : request($name))" :options="$fieldOptions[$name] ?? []" />
                </div>
            @endif
        @endforeach
    </div>
    @if($projectOptions->isEmpty())<p class="text-sm text-amber-700">Create a project before adding activities or sub-activities.</p>@endif
    @if($module === 'subactivities')<p class="text-sm text-slate-500">Choose a parent activity from the same project and stage. Create the activity first if it is not listed.</p>@endif
    @include('partials.documents-form')
    <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
        <a class="rounded-lg border border-slate-200 px-4 py-2 text-sm" href="{{ route($module.'.index') }}">Cancel</a>
        <button class="rounded-lg bg-teal px-5 py-2 text-sm font-semibold text-white">Save {{ strtolower($definition['singular']) }}</button>
    </div>
</form>

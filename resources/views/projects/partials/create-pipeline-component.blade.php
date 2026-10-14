    <article data-pipeline-row class="space-y-4 rounded-xl border border-teal-100 bg-white p-4">
        <label class="block text-sm font-semibold">Component name<select class="field mt-2" data-pipeline-name name="pipeline_components[{{ $index }}][template_id]" required>
        @foreach($pipelineTemplates as $template)<option value="{{ $template->id }}" @selected((int) $step['template_id'] === $template->id) data-order="{{ $template->sort_order }}">{{ $template->sort_order }}. {{ $template->name }}</option>@endforeach
        </select></label>
        @if($project->exists && $project->pipelineSteps->firstWhere('template_id', $step['template_id'] ?? null)?->planned_date)<p class="text-xs text-slate-500">Change this deadline through Request an extension on the Pipeline steps page.</p>@endif
        <div class="grid gap-4 sm:grid-cols-2">
        @foreach(['start_date' => 'Start date', 'planned_date' => 'End date', 'responsible_office' => 'Responsible officer / office'] as $field => $label)<label class="text-sm">{{ $label }}<input class="field mt-2" type="{{ str_ends_with($field, 'date') ? 'date' : 'text' }}" name="pipeline_components[{{ $index }}][{{ $field }}]" value="{{ $step[$field] ?? '' }}" @if($field === 'responsible_office') maxlength="255" @endif @readonly($field === 'planned_date' && $project->exists && $project->pipelineSteps->firstWhere('template_id', $step['template_id'] ?? null)?->planned_date)></label>@endforeach
        <label class="text-sm sm:col-span-2">Details<textarea class="field mt-2 !h-auto" rows="2" maxlength="10000" name="pipeline_components[{{ $index }}][remarks]">{{ $step['remarks'] ?? '' }}</textarea></label>
        </div>
        @if(!$project->exists || !$project->pipelineSteps->contains('template_id', $step['template_id'] ?? null))<button type="button" data-remove-pipeline class="text-xs text-rose-700">Remove component</button>@endif
    </article>

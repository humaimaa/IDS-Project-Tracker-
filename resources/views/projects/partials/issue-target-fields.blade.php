<div data-issue-targets class="grid gap-5 sm:grid-cols-2" data-project="{{ old('project_id', $record->project_id ?? request('project_id')) }}" data-component="{{ old('pipeline_step_id', $record->pipeline_step_id) ?: old('component_id', $record->component_id ?? $record->subcomponent?->component_id) }}" data-subcomponent="{{ old('subcomponent_id', $record->subcomponent_id) }}">
<script type="application/json" data-issue-projects>{!! json_encode($issueProjects, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<label class="block text-sm font-semibold">Project<select name="project_id" class="field mt-2" required data-issue-project><option value="">Select project</option>@foreach($issueProjects as $choice)<option value="{{ $choice['id'] }}">{{ $choice['name'] }} ({{ $choice['phase'] }})</option>@endforeach</select></label>
<label class="block text-sm font-semibold">Component<select class="field mt-2" data-issue-component disabled aria-describedby="issue-target-help"><option value="">Select project first</option></select></label>
<label class="block text-sm font-semibold">Subcomponent<select name="subcomponent_id" class="field mt-2" data-issue-subcomponent disabled><option value="">Select component first</option></select></label>
<input type="hidden" name="component_id" data-issue-component-id><input type="hidden" name="pipeline_step_id" data-issue-pipeline-id>
<p class="self-end text-xs text-slate-500" id="issue-target-help">Project-level issues do not require a component. Pipeline components have no subcomponents.</p>
</div>

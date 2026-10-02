<h4 class="font-semibold">{{ $activity['name'] }}</h4>
<p class="text-xs text-teal">{{ empty($activity['template_id']) ? 'Custom · this project only' : 'Predefined · common to all projects' }}</p>
<dl class="grid gap-3 text-sm sm:grid-cols-2">
    @foreach(['description' => 'Description', 'officer' => 'Responsible officer or agency', 'status' => 'Status', 'start' => 'Planned start', 'due' => 'Due date', 'actual_start' => 'Actual start', 'actual_completion' => 'Actual completion', 'remarks' => 'Progress remarks'] as $key => $label)
        @if(!empty($activity[$key]))<div><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="mt-1">{{ $activity[$key] }}</dd></div>@endif
    @endforeach
</dl>

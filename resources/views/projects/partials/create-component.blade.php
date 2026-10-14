<article class="rounded-xl border border-teal-100 bg-white p-4 space-y-4" data-component-row>
    <div class="flex items-center justify-between gap-3"><h3 class="font-bold text-teal">Component <span data-component-number></span></h3>@if(empty($component['id']))<button type="button" data-remove-component class="text-xs font-semibold text-rose-700">Remove component</button>@endif</div>
    @if(!empty($component['id']))
    <input type="hidden" name="components[{{ $index }}][id]" value="{{ $component['id'] }}">
    <input type="hidden" name="components[{{ $index }}][name]" value="{{ $component['name'] }}">
    <p class="font-semibold">{{ $component['name'] }}</p><p class="text-xs text-slate-500">Existing component. Add new subcomponents below; existing progress and deadlines are preserved.</p>
    @if(auth()->user()->hasPortfolioPermission('activities.update'))<a class="text-sm text-teal" href="{{ route('activities.edit', $component['id']) }}">Edit component details &rarr;</a>@endif
    @else
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="text-sm font-semibold sm:col-span-2">Component name<input class="field mt-2" name="components[{{ $index }}][name]" value="{{ $component['name'] ?? '' }}" required maxlength="255"></label>
        <label class="text-sm">Start date<input class="field mt-2" type="date" name="components[{{ $index }}][start_date]" value="{{ $component['start_date'] ?? '' }}"></label>
        <label class="text-sm">Baseline end date<input class="field mt-2" type="date" name="components[{{ $index }}][baseline_end]" value="{{ $component['baseline_end'] ?? '' }}"></label>
        <label class="text-sm sm:col-span-2">Responsible office<input class="field mt-2" name="components[{{ $index }}][responsible_office]" value="{{ $component['responsible_office'] ?? '' }}" maxlength="255"></label>
        <label class="text-sm sm:col-span-2">Details<textarea class="field mt-2 !h-auto" rows="2" name="components[{{ $index }}][remarks]" maxlength="10000">{{ $component['remarks'] ?? '' }}</textarea></label>
    </div>
    @endif
    <div class="space-y-3 border-l-2 border-teal-100 pl-4" data-subcomponent-list>
        @foreach($component['subcomponents'] ?? [] as $subIndex => $subcomponent)
            @include('projects.partials.create-subcomponent')
        @endforeach
    </div>
    @if(auth()->user()->hasPortfolioPermission('subcomponents.create'))<button type="button" data-add-subcomponent class="text-sm font-semibold text-teal">+ Add subcomponent (optional)</button>@endif
</article>

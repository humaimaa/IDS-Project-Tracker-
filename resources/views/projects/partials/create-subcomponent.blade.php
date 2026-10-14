<section data-subcomponent-row class="rounded-lg bg-teal-50 p-4 space-y-3">
    <div class="flex justify-between gap-3"><strong class="text-sm">Subcomponent</strong><button type="button" data-remove-subcomponent class="text-xs text-rose-700">Remove</button></div>
    <label class="block text-sm">Subcomponent name<input class="field mt-2" name="components[{{ $index }}][subcomponents][{{ $subIndex }}][name]" value="{{ $subcomponent['name'] ?? '' }}" required maxlength="255"></label>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="text-sm">Start date<input class="field mt-2" type="date" name="components[{{ $index }}][subcomponents][{{ $subIndex }}][start_date]" value="{{ $subcomponent['start_date'] ?? '' }}"></label>
        <label class="text-sm">Baseline end date<input class="field mt-2" type="date" name="components[{{ $index }}][subcomponents][{{ $subIndex }}][baseline_end]" value="{{ $subcomponent['baseline_end'] ?? '' }}"></label>
    </div>
</section>

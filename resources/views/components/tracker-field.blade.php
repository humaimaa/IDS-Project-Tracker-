@props(['name', 'label', 'type' => 'text', 'required' => false, 'options' => [], 'value' => null])
@php
    $value = old($name, $value);
    $selected = is_array($value) ? $value : array_filter(array_map('trim', explode(',', $value ?? '')));
@endphp
<label class="block text-sm font-semibold text-slate-600">
    {{ $label }} @if($required)<span class="text-rose-500">*</span>@endif
    @if(in_array($type, ['select', 'multiselect']))
        <select class="field mt-2 {{ $type === 'multiselect' ? 'min-h-28 py-2' : '' }}" name="{{ $name }}{{ $type === 'multiselect' ? '[]' : '' }}" @required($required) @if($type === 'multiselect') multiple @endif>
            @if($type === 'select')<option value="">Choose {{ strtolower($label) }}</option>@endif
            @foreach($options as $option)<option value="{{ $option }}" @selected($type === 'multiselect' ? in_array($option, $selected) : $value === $option)>{{ $option }}</option>@endforeach
        </select>
        @if($type === 'multiselect')<span class="mt-1 block text-xs font-normal text-slate-500">Choose one or more. Hold Ctrl or Command to select multiple items.</span>@endif
    @elseif($type === 'textarea')
        <textarea class="field mt-2 min-h-28 py-2" name="{{ $name }}" @required($required)>{{ $value }}</textarea>
    @else
        <input class="field mt-2" type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" @required($required) @if($type === 'number') min="0" step="0.01" @endif @if($name === 'currency') placeholder="e.g. PKR, USD" maxlength="3" @endif>
    @endif
    @error($name)<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
</label>

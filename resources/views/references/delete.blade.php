@extends('layouts.app')

@section('content')
<section class="panel mx-auto max-w-xl space-y-5 p-6">
    <h1 class="font-display text-2xl font-extrabold">Delete {{ strtolower($definition['singular']) }}</h1>
    <p>Delete “{{ $record->data['name'] }}”? This action cannot be undone.</p>
    <form class="flex justify-end gap-3" method="post" action="{{ route($module.'.destroy', $record) }}">
        @csrf
        @method('DELETE')
        <a class="rounded-lg border border-slate-200 px-4 py-2 text-sm" href="{{ route($module.'.show', $record) }}">Cancel</a>
        <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Delete {{ strtolower($definition['singular']) }}</button>
    </form>
</section>
@endsection

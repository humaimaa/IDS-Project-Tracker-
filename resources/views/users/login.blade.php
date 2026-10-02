@extends('layouts.app')

@section('content')
<form class="panel mx-auto max-w-md space-y-5 p-6" method="post" action="{{ route('login.store') }}">
    @csrf
    <h1 class="font-display text-2xl font-extrabold">Log in</h1>
    <x-tracker-field name="email" label="Email" type="email" :required="true" />
    <label class="block text-sm font-semibold text-slate-600">
        Password
        <input class="field mt-2" type="password" name="password" autocomplete="current-password" required>
        @error('password')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
    </label>
    <button class="rounded-lg bg-teal px-5 py-2 text-sm font-semibold text-white">Log in</button>
</form>
@endsection

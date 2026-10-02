@extends('layouts.app')

@section('content')
<form class="panel mx-auto max-w-md space-y-5 p-6" method="post" action="{{ route('account.setup') }}">
    @csrf
    <h1 class="font-display text-2xl font-extrabold">Set up your account</h1>
    <p class="text-sm text-slate-500">Create the first login account for this tracker.</p>
    <x-tracker-field name="name" label="User name" :required="true" value="Aina Khan" />
    <x-tracker-field name="email" label="Email" type="email" :required="true" />
    <label class="block text-sm font-semibold text-slate-600">
        Password (at least 12 characters)
        <input class="field mt-2" type="password" name="password" autocomplete="new-password" minlength="12" required>
        @error('password')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
    </label>
    <label class="block text-sm font-semibold text-slate-600">
        Confirm password
        <input class="field mt-2" type="password" name="password_confirmation" autocomplete="new-password" required>
    </label>
    <button class="rounded-lg bg-teal px-5 py-2 text-sm font-semibold text-white">Create account</button>
</form>
@endsection

@extends('layouts.app')

@section('content')
<h1 class="mb-5 font-display text-2xl font-extrabold">Account</h1>
<form class="panel max-w-2xl space-y-5 p-6" method="post" action="{{ route('account.update') }}">
    @csrf
    @method('PUT')
    <p class="text-sm text-slate-500">Update your name and email. Leave the new password blank to keep your current password.</p>
    <x-tracker-field name="name" label="User name" :required="true" :value="$user->name" />
    <x-tracker-field name="email" label="Email" type="email" :required="true" :value="$user->email" />
    <label class="block text-sm font-semibold text-slate-600">
        Current password
        <input class="field mt-2" type="password" name="current_password" autocomplete="current-password">
        <span class="mt-1 block text-xs font-normal">Required when changing your email or password.</span>
        @error('current_password')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
    </label>
    <label class="block text-sm font-semibold text-slate-600">
        New password
        <input class="field mt-2" type="password" name="password" autocomplete="new-password" minlength="12">
        <span class="mt-1 block text-xs font-normal">Use at least 12 characters.</span>
        @error('password')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
    </label>
    <label class="block text-sm font-semibold text-slate-600">
        Confirm new password
        <input class="field mt-2" type="password" name="password_confirmation" autocomplete="new-password">
    </label>
    <button class="rounded-lg bg-teal px-5 py-2 text-sm font-semibold text-white">Save account</button>
</form>
@endsection

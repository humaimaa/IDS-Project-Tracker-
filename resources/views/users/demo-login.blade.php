@extends('layouts.public')
@section('content')
<main class="landing-login">
    <a href="{{ route('home') }}" class="login-home">← Back to home</a>
    <section class="login-card" aria-labelledby="login-heading">
        <img class="mx-auto mb-6 h-20 w-auto" src="{{ asset('images/ids-logo.png') }}" alt="Government of Khyber Pakhtunkhwa">
        <p class="landing-eyebrow text-center">IDS PROJECT TRACKER</p>
        <h1 id="login-heading" class="font-display text-center text-3xl font-extrabold">Welcome back</h1>
        <p class="landing-muted mb-7 mt-3 text-center">Explore your projects and their progress.</p>
        <form action="{{ route('dashboard') }}" method="get" class="space-y-5">
            <label class="block text-sm font-semibold">Email<input class="field mt-2" type="email" autocomplete="email" placeholder="you@example.com"></label>
            <label class="block text-sm font-semibold">Password<input class="field mt-2" type="password" autocomplete="current-password" placeholder="Enter your password"></label>
            <button class="landing-button w-full justify-center" type="submit" formnovalidate>Log in <span aria-hidden="true">→</span></button>
        </form>
        <p class="mt-4 text-center text-xs text-slate-500">Demo access · You can leave both fields empty.</p>
        <a class="mt-7 block text-center text-sm font-semibold text-teal underline" href="{{ route('account.login') }}">Sign in to your account</a>
    </section>
</main>
@endsection

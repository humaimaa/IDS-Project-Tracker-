@extends('layouts.guest')
@section('content')
<section class="panel space-y-5 p-6">
    <h1 class="font-display text-2xl font-extrabold">Confirm your new email</h1>
    <p class="text-sm text-slate-600">Change your sign-in email from <strong>{{ $user->email }}</strong> to <strong>{{ $user->pending_email }}</strong>?</p>
    @if($errors->any())<p role="alert" class="text-sm text-rose-700">{{ $errors->first() }}</p>@endif
    <form method="post" action="{{ $confirmationUrl }}">@csrf<button class="portfolio-primary">Verify and change email</button></form>
    <a href="{{ route('account.edit') }}" class="block text-sm text-teal">Back to account</a>
</section>
@endsection

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#06464b">
    <title>Sign in | IDS Project Tracker</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-teal-50 font-sans antialiased">
    <main class="mx-auto flex min-h-screen max-w-lg flex-col justify-center gap-8 px-5 py-12">
        <a href="{{ route('home') }}" class="text-center text-teal"><strong class="font-display text-4xl font-extrabold">IDS</strong><span class="mt-2 block text-sm">International Development Section</span></a>
        @if(session('success'))<p role="status" class="rounded-xl bg-white p-4 text-sm text-teal">{{ session('success') }}</p>@endif
        @yield('content')
        <a href="{{ route('home') }}" class="text-center text-sm text-teal">&larr; Back to home</a>
    </main>
</body>
</html>

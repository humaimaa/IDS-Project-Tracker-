<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#06464b">
    <title>Project Tracker | International Development Section</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased {{ request()->routeIs('dashboard') ? 'dashboard-theme' : '' }}">
    <div class="mobile-backdrop" id="mobile-backdrop" aria-hidden="true"></div>
    <div class="min-h-screen">
        @include('partials.sidebar')
        <div class="min-h-screen min-w-0" id="app-shell">
            @include('partials.header')
            <main class="mx-auto max-w-[1600px] px-4 pb-10 pt-5 sm:px-6 lg:px-7">
                @if(session('success'))<p class="panel mb-4 p-4 text-teal" role="status">{{ session('success') }}</p>@endif
                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>

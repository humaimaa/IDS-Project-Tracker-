@extends('layouts.app')
@section('content')
<section class="space-y-5"><h1 class="font-display text-2xl font-extrabold">Activity logs</h1><p class="text-sm text-slate-500">Who changed what and when. Passwords are redacted.</p>
@forelse($logs as $log)<article class="panel p-5"><div class="flex flex-wrap justify-between gap-3"><strong>{{ $log->actor_name }} · {{ $log->action }}</strong><time class="text-xs text-slate-500">{{ $log->created_at->format('d M Y H:i:s') }}</time></div><p class="mt-2 text-sm">{{ $log->subject_name }} · {{ $log->module }}</p><details class="mt-3 text-sm"><summary class="cursor-pointer text-teal">View changes</summary><pre class="mt-3 overflow-auto rounded-lg bg-slate-50 p-3 text-xs">{{ json_encode($log->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details></article>@empty<p class="panel p-5">No changes recorded yet.</p>@endforelse{{ $logs->links() }}</section>
@endsection

@extends('layouts.app')
@section('content')
<section class="portfolio-page space-y-5">
    <div><p class="eyebrow mb-2">Workspace history</p><h1 class="font-display text-2xl font-extrabold">Activity logs</h1><p class="mt-2 text-sm text-slate-500">Demo activity history for presentation. These sample entries illustrate who changed what and when; no real changes are recorded. Times shown in Pakistan time.</p></div>
    <form class="panel grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-4" method="get" action="{{ route('logs.index') }}">
        <label class="text-xs font-semibold text-slate-500">Search<input class="field mt-2" name="search" value="{{ request('search') }}" placeholder="Record or person"></label>
        <label class="text-xs font-semibold text-slate-500">Action<select name="action" class="field mt-2"><option value="">All actions</option>@foreach(['created', 'updated', 'deleted'] as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ ucfirst($action) }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-500">Area<select name="module" class="field mt-2"><option value="">All areas</option>@foreach($modules as $module)<option value="{{ $module }}" @selected(request('module') === $module)>{{ ucwords(str_replace('_', ' ', $module)) }}</option>@endforeach</select></label>
        <div class="flex items-end gap-4"><button class="portfolio-primary">Apply filters</button><a href="{{ route('logs.index') }}" class="py-3 text-xs font-semibold text-teal">Reset</a></div>
    </form>
    <p class="text-xs text-slate-500">{{ $logs->total() }} entries &middot; Newest first</p>
    <div class="space-y-3">
    @forelse($logs as $log)
        <details class="panel p-5 audit-entry">
            <summary class="cursor-pointer"><span class="audit-summary"><span class="portfolio-pill audit-action" data-action="{{ $log->action }}">{{ ucfirst($log->action) }}</span><span class="min-w-0"><strong class="block break-words text-sm">{{ $log->subject_name }}</strong><span class="mt-1 block text-xs text-slate-500">{{ ucwords(str_replace('_', ' ', $log->module)) }} #{{ $log->subject_id }} &middot; {{ $log->actor_name }}@if($log->actor_id) (User #{{ $log->actor_id }})@endif</span></span><time class="text-xs text-slate-500" datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->timezone('Asia/Karachi')->format('d M Y, g:i:s A') }}</time></span></summary>
            <div class="mt-4 overflow-x-auto"><table class="portfolio-table"><thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead><tbody>
            @foreach($log->changes as $field => $change)
                <tr><td>{{ ucwords(str_replace('_', ' ', $field)) }}</td>@foreach(['before', 'after'] as $side)<td class="audit-value">{{ is_array($change[$side]) ? json_encode($change[$side], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (is_bool($change[$side]) ? ($change[$side] ? 'Yes' : 'No') : ($change[$side] ?? 'Not set')) }}</td>@endforeach</tr>
            @endforeach
            </tbody></table></div>
        </details>
    @empty
        <div class="panel p-8 text-center text-sm text-slate-500">No sample logs match this selection. Try another filter.</div>
    @endforelse
    </div>
    {{ $logs->links() }}
</section>
@endsection

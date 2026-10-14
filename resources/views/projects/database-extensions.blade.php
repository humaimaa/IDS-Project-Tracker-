@extends('layouts.app')
@section('content')
<section class="portfolio-page space-y-5">
    <div><h1 class="font-display text-2xl font-extrabold">Deadline extensions</h1><p class="mt-2 text-sm text-slate-500">Your requests and reviews assigned to you. You cannot approve extensions on your own projects.</p></div>
    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @forelse($extensions as $extension)
        @php($target = $extension->pipelineStep ?? $extension->component ?? $extension->subcomponent)
        @php($project = $extension->pipelineStep?->project ?? $extension->component?->project ?? $extension->subcomponent->component->project)
        <article class="panel p-5">
            <div class="flex flex-wrap justify-between gap-3"><div><p class="text-xs text-teal">{{ $project->name }}</p><h2 class="mt-2 font-bold">{{ $target->name }}</h2></div><span class="portfolio-pill">{{ $extension->status }}</span></div>
            <p class="mt-4 text-sm">{{ $extension->old_end_date->format('d M Y') }} &rarr; {{ $extension->requested_end_date->format('d M Y') }}</p>
            <p class="mt-3 text-sm text-slate-600">{{ $extension->reason }}</p>
            <p class="mt-2 text-xs text-slate-500">Requested by {{ $extension->requester->name }} · Reviewer: {{ $extension->reviewer->name }}</p>
            @if($extension->impact)<p class="mt-3 text-sm text-slate-600">Impact: {{ $extension->impact }}</p>@endif
            @foreach($extension->decisions as $decision)<p class="mt-3 rounded-lg bg-teal-50 p-3 text-sm">{{ $decision->decision }} · {{ $decision->remarks }}</p>@endforeach
            @if($extension->status === 'Pending' && auth()->id() === $extension->reviewer_id && auth()->user()->canApproveExtension($project->id))
            <form method="post" action="{{ route('extensions.decide', $extension) }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto_auto]">@csrf<label class="text-xs">Decision remarks<input name="remarks" class="field mt-1" required maxlength="5000"></label><label class="text-xs">Decision<select name="decision" class="field mt-1"><option>Approved</option><option>Rejected</option><option>Returned</option></select></label><button class="portfolio-primary self-end">Record decision</button></form>
            @endif
            @if($extension->status === 'Pending' && auth()->id() === $extension->requester_id && auth()->user()->hasPortfolioPermission('extensions.withdraw'))
            <form method="post" action="{{ route('extensions.withdraw', $extension) }}" class="mt-3">@csrf<button class="text-sm font-semibold text-rose-700">Withdraw request</button></form>
            @endif
        </article>
    @empty<div class="panel p-5 text-sm text-slate-500">No extension requests assigned to you yet.</div>@endforelse
    {{ $extensions->links() }}
</section>
@endsection

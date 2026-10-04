<button type="button" class="project-chat-launcher" id="project-chat-toggle" aria-controls="project-comments" aria-expanded="false" aria-label="Open project comments" title="Project comments">
    <svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H7l-5 3 1.5-6A8.5 8.5 0 1 1 21 11.5Z"/><path d="M7 9h10M7 13h6"/></svg>
    <span class="project-chat-badge" data-chat-badge hidden></span>
</button>
<span class="sr-only" data-chat-announcement role="status" aria-live="polite"></span>
<section class="project-chat-popup" id="project-comments" role="dialog" aria-modal="false" aria-labelledby="comments-heading" tabindex="-1" hidden data-read-url="{{ route('dashboard.comments.read') }}" data-read-token="{{ csrf_token() }}" data-project-chat data-project-key="{{ $projectKey }}" data-user-id="{{ auth()->id() ?? 'guest' }}" data-refresh-url="{{ $trackingUrl }}" data-context="{{ $context }}" data-initial-open="{{ $errors->any() ? 'true' : 'false' }}">
    <script type="application/json" data-chat-metadata>@json($commentMetadata)</script>
<div class="project-chat-header"><div><p class="text-[10px] uppercase tracking-widest text-teal-100/70">Project discussion</p><h2 class="mt-1 font-display text-base font-bold" id="comments-heading">Comments & replies</h2></div><button type="button" class="project-chat-close" data-chat-close aria-label="Close project comments"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg></button></div>
    <div class="project-chat-body">
    <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-2"><p class="text-[11px] text-slate-400" data-chat-status role="status">Project conversation</p><button type="button" class="text-xs font-semibold text-teal" data-chat-refresh>Refresh</button></div>
    <form method="get" action="{{ $trackingUrl }}#project-comments" class="space-y-2 border-b border-line bg-slate-50/60 p-4">
        <label class="block text-xs font-semibold text-slate-500" for="discussion-context">Show discussion for</label>
        <div class="flex gap-2"><select class="field min-w-0 !text-xs" name="context" id="discussion-context">@foreach($contexts as $key => $label)<option value="{{ $key }}" @selected($context === $key)>{{ $label }}</option>@endforeach</select><button class="rounded-lg bg-teal px-3 text-xs font-semibold text-white">Show</button></div>
    </form>
    @if($errors->any())<div role="alert" class="m-4 rounded-lg bg-rose-50 p-3 text-xs text-rose-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @auth
        <form method="post" action="{{ $commentsUrl }}" class="space-y-3 border-b border-line p-4">
            @csrf
            <input type="hidden" name="context" value="{{ $context }}">
            <label for="project-comment-body" class="block text-xs font-semibold text-slate-600">Add a comment · {{ $contexts[$context] }}</label>
            <textarea id="project-comment-body" name="body" required maxlength="3000" rows="3" class="field !h-auto resize-y py-3 !text-xs" placeholder="Share an update or ask a question…">{{ old('parent_id') ? '' : old('body') }}</textarea>
            <div class="flex items-center justify-between gap-2"><span class="truncate text-[11px] text-slate-400">Posting as {{ auth()->user()->name }}</span><button class="shrink-0 rounded-lg bg-teal px-3 py-2 text-xs font-semibold text-white">Post comment</button></div>
        </form>
    @else
        <p class="border-b border-line p-4 text-xs leading-relaxed text-slate-500"><a class="font-semibold text-teal underline" href="{{ route('login') }}">Log in</a> to add comments and reply to the project team.</p>
    @endauth
    <div data-chat-threads>
        @include('projects.partials.comment-threads')
    </div>
</div>
</section>

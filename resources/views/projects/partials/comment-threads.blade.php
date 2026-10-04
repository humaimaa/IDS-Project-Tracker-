    <div class="discussion-threads divide-y divide-line">
        @forelse($threads as $comment)
            <article class="space-y-3 p-4" id="comment-{{ $comment->id }}">
                <div class="flex items-center gap-2"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-teal/10 text-xs font-bold text-teal" aria-hidden="true">{{ mb_substr($comment->data['author_name'], 0, 1) }}</span><div class="min-w-0"><p class="truncate text-xs font-bold">{{ $comment->data['author_name'] }}</p><time class="text-[10px] text-slate-400" datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->format('d M Y, H:i') }}</time></div></div>
                <span class="inline-block rounded-md bg-slate-100 px-2 py-1 text-[10px] text-slate-500">{{ $contexts[$comment->data['context']] ?? 'Previous activity' }}</span>
                <p class="whitespace-pre-wrap break-words text-xs leading-relaxed text-slate-600">{{ $comment->data['body'] }}</p>
                @foreach($replies->get((string) $comment->id, collect()) as $reply)
                    <div class="ml-3 space-y-1 border-l-2 border-teal/20 pl-3"><p class="text-xs font-semibold">{{ $reply->data['author_name'] }} <time class="ml-1 text-[10px] font-normal text-slate-400">{{ $reply->created_at->format('d M, H:i') }}</time></p><p class="whitespace-pre-wrap break-words text-xs leading-relaxed text-slate-600">{{ $reply->data['body'] }}</p></div>
                @endforeach
                @auth
                    <details @if((string) old('parent_id') === (string) $comment->id) open @endif><summary class="cursor-pointer text-xs font-semibold text-teal">Reply</summary><form method="post" action="{{ $commentsUrl }}" class="mt-3 space-y-2">@csrf<input type="hidden" name="parent_id" value="{{ $comment->id }}"><input type="hidden" name="context" value="{{ $comment->data['context'] }}"><label class="sr-only" for="reply-{{ $comment->id }}">Reply to {{ $comment->data['author_name'] }}</label><textarea class="field !h-auto py-2 !text-xs" id="reply-{{ $comment->id }}" name="body" rows="2" required maxlength="3000" placeholder="Write a reply…">{{ (string) old('parent_id') === (string) $comment->id ? old('body') : '' }}</textarea><button class="rounded-lg bg-teal/10 px-3 py-2 text-xs font-semibold text-teal">Post reply</button></form></details>
                @endauth
            </article>
        @empty
            <div class="px-5 py-10 text-center"><div class="mx-auto mb-3 grid size-10 place-items-center rounded-full bg-teal/5 text-xl text-teal" aria-hidden="true">&#9776;</div><p class="text-sm font-semibold text-slate-600">Start the conversation</p><p class="mt-2 text-xs leading-relaxed text-slate-400">Updates and replies for this project will appear here.</p></div>
        @endforelse
    </div>

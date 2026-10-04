<div class="comment-notifications" data-comment-notifications>
@if($recentComments->isNotEmpty())
    <div class="comment-notification-window" tabindex="0" aria-label="Unseen project comments"><div class="comment-notification-track">
        @for($copy = 0; $copy < 2; $copy++)<div class="comment-notification-group" @if($copy) aria-hidden="true" @endif>
        @foreach($recentComments as $comment)<a href="{{ $comment['url'] }}" class="comment-notification" @if($copy) tabindex="-1" @endif><span class="comment-notification-dot" aria-hidden="true"></span><span class="min-w-0"><small>{{ $comment['reply'] ? 'New reply to a comment' : 'New comment' }}</small><strong>{{ $comment['project'] }}</strong></span><span class="ml-auto text-teal" aria-hidden="true">&rarr;</span></a>@endforeach
        </div>@endfor
    </div></div>
@else<p class="px-5 py-7 text-xs text-slate-500">You’re up to date. No unseen comments or replies.</p>@endif
</div>

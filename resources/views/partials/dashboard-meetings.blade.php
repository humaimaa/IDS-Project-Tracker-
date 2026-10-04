<section class="meeting-news panel" aria-label="Meeting highlights">
    <div class="meeting-news-heading"><span class="meeting-news-dot" aria-hidden="true"></span><strong>Meeting highlights</strong><span class="portfolio-pill">{{ $unseenMeetings->count() }}</span></div>
    @if($unseenMeetings->isNotEmpty())
    <div class="meeting-news-window"><div class="meeting-news-track" style="--meeting-scroll-duration: {{ max(30, $unseenMeetings->count() * 10) }}s">@for($copy = 0; $copy < 2; $copy++)<div class="meeting-news-group" @if($copy) aria-hidden="true" @endif>@foreach($unseenMeetings as $meeting)<a href="{{ $meeting['url'] }}" @if($copy) tabindex="-1" @endif><span>{{ $meeting['name'] }}</span><small>{{ $meeting['date']?->format('d M Y · g:i A') ?? 'Date not set' }}{{ $meeting['demo'] ? ' · Demo' : '' }}</small><span aria-hidden="true">↗</span></a>@endforeach</div>@endfor</div></div>
    @else<p class="px-4 py-3 text-xs text-slate-500">You’re up to date. No new meeting highlights.</p>@endif
</section>

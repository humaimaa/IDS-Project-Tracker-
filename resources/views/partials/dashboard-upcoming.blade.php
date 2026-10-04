<div class="upcoming-meeting-panel {{ count($upcomingMeetings) > 2 ? 'has-overflow' : '' }}">
@if(count($upcomingMeetings) > 2)<button type="button" class="upcoming-scroll-pause" data-upcoming-pause aria-pressed="false">Pause scrolling</button>@endif
<div class="upcoming-meeting-list"><div class="upcoming-meeting-track" style="--upcoming-duration: {{ max(18, count($upcomingMeetings) * 6) }}s">
@for($copy = 0; $copy < (count($upcomingMeetings) > 2 ? 2 : 1); $copy++)
<div class="upcoming-meeting-group" @if($copy > 0) aria-hidden="true" @endif>
@forelse($upcomingMeetings as $meeting)<a class="upcoming-meeting" @if($copy > 0) tabindex="-1" @endif href="{{ $meeting['url'] }}"><span class="meeting-calendar"><strong>{{ $meeting['date']->format('d') }}</strong><small>{{ $meeting['date']->format('M') }}</small></span><span class="min-w-0"><strong class="block text-sm">{{ $meeting['name'] }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $meeting['date']->format('D, g:i A') }} · Pakistan time</span><span class="mt-1 block text-xs text-slate-400">{{ $meeting['location'] }}</span>@if($meeting['demo'])<span class="text-[10px] text-teal">Demo meeting</span>@endif</span><span class="ml-auto text-teal" aria-hidden="true">↗</span></a>@empty<p class="py-8 text-sm text-slate-500">No upcoming meetings scheduled.</p>@endforelse
</div>
@endfor
</div></div></div>

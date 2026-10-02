            <header class="app-header sticky top-0 z-30 flex h-[88px] items-center justify-between gap-4 px-4 backdrop-blur-md sm:px-6 lg:px-7">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" class="icon-button shrink-0 md:hidden" id="mobile-menu" aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation" title="Open navigation"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                    <button type="button" class="icon-button hidden shrink-0 md:inline-flex" id="collapse-sidebar" aria-controls="sidebar" aria-expanded="true" aria-label="Close sidebar" title="Close sidebar"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/></svg></button>
                    <div class="min-w-0">
                        <p class="truncate font-display text-base font-extrabold text-ink sm:text-lg">International Development Section</p>
                        <p class="mt-0.5 hidden text-xs text-[#175663] sm:block">Civil Secretariat, Peshawar</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <label class="relative hidden w-56 lg:block xl:w-72">
                        <svg class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input class="field !h-9 !pl-9 !text-xs" id="global-search" data-search-url="{{ route('dashboard') }}" placeholder="Search projects by name or number..." aria-label="Search projects">
                    </label>
                    <div class="hidden h-8 w-px bg-slate-200 sm:block"></div>

                    <div class="grid size-9 place-items-center rounded-full bg-teal text-xs font-bold text-white">{{ collect(explode(' ', auth()->user()?->name ?? 'Aina Khan'))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('') }}</div>
                    <div class="hidden text-xs xl:block"><p class="font-bold">{{ auth()->user()?->name ?? 'Aina Khan' }}</p><p class="mt-1 text-[11px] text-slate-500">{{ $sidebarRole ?? 'Section Officer' }}</p></div>
                    @include('partials.account-menu', ['placement' => 'below'])
                </div>
            </header>

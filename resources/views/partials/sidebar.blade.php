        <aside class="sidebar flex flex-col bg-[#06464b] text-white" id="sidebar" aria-label="Main navigation" tabindex="-1">
            <div class="sidebar-brand flex h-[88px] items-center gap-3 border-b border-white/10 px-5">
                <div class="sidebar-monogram grid size-10 shrink-0 place-items-center rounded-xl bg-white/10 font-display text-lg font-extrabold tracking-wide">IDS</div>
                <div class="brand-copy min-w-0">
                    <p class="font-display text-sm font-bold">Project Tracker</p>
                    <p class="mt-0.5 text-[10px] text-teal-100/55">International Development</p>
                </div>
            </div>

            <button type="button" class="sidebar-close" id="close-sidebar" aria-label="Close navigation" title="Close navigation"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg></button>
            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-5" id="sidebar-nav" aria-label="Workspace">
                @if(config('portfolio.enabled'))
                <p class="sidebar-caption mb-2 px-3 text-[9px] font-bold uppercase tracking-[0.18em] text-teal-100/40">Workspace</p>
                @foreach(['dashboard' => 'Dashboard', 'projects' => 'Projects', 'sectors' => 'Sectors', 'partners' => 'Development partners', 'issues' => 'Issues', 'extensions' => 'Extensions', 'meetings' => 'Meetings', 'users' => 'Users', 'roles' => 'Roles', 'logs' => 'Logs'] as $module => $label)
                    @if(auth()->user()?->hasPortfolioPermission($module.'.view') || ($module === 'projects' && (auth()->user()->hasPortfolioPermission('financial-reports.view') || auth()->user()->hasPortfolioPermission('financial-reports.create'))) || (in_array($module, ['projects', 'sectors', 'partners', 'issues', 'meetings', 'users', 'roles']) && auth()->user()?->hasPortfolioPermission($module.'.create')))
                    @if(in_array($module, ['projects', 'sectors', 'partners', 'issues', 'activities', 'meetings', 'users', 'roles']))
                    <div class="nav-group" data-group="{{ $module }}">
                    <button type="button" class="nav-link" data-toggle-group="{{ $module }}" title="{{ $label }}">
                        <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">@switch($module)
                            @case('dashboard')<path d="m3 10 9-7 9 7v10h-6v-7H9v7H3z"/>@break
                            @case('projects')<path d="M3 7V5h7l2 2h9v13H3z"/>@break
                            @case('issues')<path d="m12 3 10 18H2zM12 9v5m0 3v.1"/>@break
                            @case('activities')<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>@break
                            @case('extensions')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
                            @case('meetings')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>@break
                            @case('users')<circle cx="10" cy="7" r="4"/><path d="M3 21v-3a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v3m1-17a4 4 0 0 1 0 8m1 3a5 5 0 0 1 3 5"/>@break
                            @case('roles')<path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6zM8 12l3 3 5-6"/>@break
                            @default<rect x="4" y="3" width="16" height="18" rx="3"/><path d="M8 8h8M8 12h8M8 16h5"/>
                        @endswitch</svg>
                        <span class="nav-label flex-1 text-left">{{ $label }}</span>
                        <span class="nav-chevron text-xs">&#8964;</span>
                    </button>

                    <div class="nav-group-content ml-[21px] hidden border-l border-white/10 py-1 pl-2" data-content="{{ $module }}">
                        @if($module === 'projects')
                            @if(auth()->user()->hasPortfolioPermission('financial-reports.create'))<a class="nav-child {{ request()->routeIs('financial-information.create') ? 'active' : '' }}" href="{{ route('financial-information.create') }}">Add financial information</a>@endif
                            @if(auth()->user()->hasPortfolioPermission('financial-reports.view'))<a class="nav-child {{ request()->routeIs('financial-reports.*', 'financial-information.edit') ? 'active' : '' }}" href="{{ route('financial-reports.index') }}">Manage financial information</a>@endif
                        @endif
                        @if(auth()->user()->hasPortfolioPermission($module.'.create'))<a class="nav-child {{ request()->routeIs($module.'.create') ? 'active' : '' }}" href="{{ route($module.'.create') }}">Add {{ $module === 'projects' ? 'Project' : \Illuminate\Support\Str::singular($label) }}</a>@endif
                        @if(auth()->user()->hasPortfolioPermission($module.'.view'))<a class="nav-child {{ request()->routeIs($module.'.*') && !request()->routeIs($module.'.create') ? 'active' : '' }}" href="{{ route($module.'.index') }}">Manage {{ $label }}</a>@endif
                    </div></div>
                    @else
                    <a class="nav-link relative {{ request()->routeIs($module, $module.'.*') ? 'active' : '' }}" href="{{ route($module === 'dashboard' ? 'dashboard' : $module.'.index') }}" title="{{ $label }}">
                        <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">@switch($module)
                            @case('dashboard')<path d="m3 10 9-7 9 7v10h-6v-7H9v7H3z"/>@break
                            @case('projects')<path d="M3 7V5h7l2 2h9v13H3z"/>@break
                            @case('issues')<path d="m12 3 10 18H2zM12 9v5m0 3v.1"/>@break
                            @case('activities')<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>@break
                            @case('extensions')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
                            @case('meetings')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>@break
                            @case('users')<circle cx="10" cy="7" r="4"/><path d="M3 21v-3a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v3m1-17a4 4 0 0 1 0 8m1 3a5 5 0 0 1 3 5"/>@break
                            @case('roles')<path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6zM8 12l3 3 5-6"/>@break
                            @default<rect x="4" y="3" width="16" height="18" rx="3"/><path d="M8 8h8M8 12h8M8 16h5"/>
                        @endswitch</svg>
                        <span class="nav-label flex-1">{{ $label }}</span>
                        @if($module === 'extensions' && $pendingExtensionCount > 0)
                        <span class="extension-count-badge" aria-label="{{ $pendingExtensionCount }} pending extension requests">{{ $pendingExtensionCount > 99 ? '99+' : $pendingExtensionCount }}</span>
                        @endif
                    </a>
                    @endif
                    @endif
                @endforeach
                @php
                    $dataGroups = [
                        'Components' => ['pipeline-templates'],
                        'Reference data' => ['districts'],
                    ];
                    $dataGroups = collect($dataGroups)->map(fn ($modules) => collect($modules)->filter(fn ($module) => auth()->user()->hasPortfolioPermission($module.'.view') || auth()->user()->hasPortfolioPermission($module.'.create')))->filter(fn ($modules) => $modules->isNotEmpty());
                @endphp
                @if($dataGroups->isNotEmpty())
                <div class="nav-group" data-group="reference-data">
                    <button type="button" class="nav-link" data-toggle-group="reference-data" title="Data Management"><span class="size-[18px] shrink-0" aria-hidden="true">&#9881;</span><span class="nav-label flex-1 text-left">Data Management</span><span class="nav-chevron text-xs">&#8964;</span></button>
                    <div class="nav-group-content ml-[21px] hidden border-l border-white/10 py-1 pl-2" data-content="reference-data">
                    @foreach($dataGroups as $group => $modules)
                        <div class="nav-group" data-group="data-{{ Str::slug($group) }}">
                            <button type="button" class="nav-link" data-toggle-group="data-{{ Str::slug($group) }}"><span class="nav-label flex-1 text-left">{{ $group }}</span><span class="nav-chevron text-xs">&#8964;</span></button>
                            <div class="nav-group-content hidden pl-3" data-content="data-{{ Str::slug($group) }}">
                            @foreach($modules as $module)
                                @php($definition = config('portfolio_records.'.$module))
                                @if(auth()->user()->hasPortfolioPermission($module.'.view'))<a class="nav-child {{ request()->routeIs($module.'.*') ? 'active' : '' }}" href="{{ route($module.'.index') }}">Manage {{ $definition['title'] }}</a>@endif
                                @if(auth()->user()->hasPortfolioPermission($module.'.create') && !($definition['existing_only'] ?? false) && $module !== 'districts')<a class="nav-child" href="{{ route($module.'.create') }}">Add {{ Str::singular($definition['title']) }}</a>@endif
                            @endforeach
                            </div>
                        </div>
                    @endforeach
                    </div>
                </div>
                @endif
                @else

                <p class="sidebar-caption mb-2 px-3 text-[9px] font-bold uppercase tracking-[0.18em] text-teal-100/40">Workspace</p>
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" title="Dashboard">
                    <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>
                    <span class="nav-label flex-1">Dashboard</span>
                </a>

                <div class="nav-group" data-group="projects">
                    <button class="nav-link" data-toggle-group="projects" title="Projects">
                        <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v1M3 7v11a2 2 0 0 0 2 2h4m-6-5 5-5h12l-5 8H3"/></svg>
                        <span class="nav-label flex-1 text-left">Projects</span><span class="nav-chevron text-xs">⌄</span>
                    </button>
                    <div class="nav-group-content ml-[21px] hidden border-l border-white/10 py-1 pl-2" data-content="projects">
                        <a class="nav-child {{ request()->routeIs('projects.create') ? 'active' : '' }}" href="{{ route('projects.create') }}">＋ <span>Add project</span></a>
                        <a class="nav-child {{ request()->routeIs('projects.index', 'projects.show', 'projects.details', 'projects.overview', 'dashboard.projects.show', 'projects.edit', 'projects.sample-*') ? 'active' : '' }}" href="{{ route('projects.index') }}">≡ <span>Manage projects</span></a>
                    </div>
                </div>

                <div class="nav-group" data-group="activities">
                    <button class="nav-link" data-toggle-group="activities" title="Activities">
                        <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                        <span class="nav-label flex-1 text-left">{{ config('portfolio.enabled') ? 'Components' : 'Activities' }}</span><span class="nav-chevron text-xs">⌄</span>
                    </button>
                    <div class="nav-group-content ml-[21px] hidden border-l border-white/10 py-1 pl-2" data-content="activities">
                        <a class="nav-child {{ request()->routeIs('activities.create') ? 'active' : '' }}" href="{{ route('activities.create') }}">＋ <span>Add activity</span></a>
                        <a class="nav-child {{ request()->routeIs('activities.index', 'activities.show', 'activities.edit', 'activities.sample-*') ? 'active' : '' }}" href="{{ route('activities.index') }}">≡ <span>Manage activities</span></a>
                    </div>
                </div>

                <div class="nav-group" data-group="meetings">
                    <button class="nav-link" data-toggle-group="meetings" title="Meetings">
                        <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h3v3H8z"/></svg>
                        <span class="nav-label flex-1 text-left">Meetings</span><span class="nav-chevron text-xs">⌄</span>
                    </button>
                    <div class="nav-group-content ml-[21px] hidden border-l border-white/10 py-1 pl-2" data-content="meetings">
                        <a class="nav-child {{ request()->routeIs('meetings.create') ? 'active' : '' }}" href="{{ route('meetings.create') }}">＋ <span>Schedule meeting</span></a>
                        <a class="nav-child {{ request()->routeIs('meetings.index', 'meetings.show', 'meetings.edit', 'meetings.sample-*') ? 'active' : '' }}" href="{{ route('meetings.index') }}">≡ <span>Manage meetings</span></a>
                    </div>
                </div>

                <div class="nav-group" data-group="users">
                    <button class="nav-link" data-toggle-group="users" title="Users">
                        <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m8-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm14 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span class="nav-label flex-1 text-left">Users</span><span class="nav-chevron text-xs">⌄</span>
                    </button>
                    <div class="nav-group-content ml-[21px] hidden border-l border-white/10 py-1 pl-2" data-content="users">
                        <a class="nav-child {{ request()->routeIs('users.create') ? 'active' : '' }}" href="{{ route('users.create') }}">＋ <span>Add user</span></a>
                        <a class="nav-child {{ request()->routeIs('users.index', 'users.show', 'users.edit', 'users.sample-*') ? 'active' : '' }}" href="{{ route('users.index') }}">≡ <span>Manage users</span></a>
                    </div>
                </div>

                <div class="nav-group" data-group="roles">
                    <button class="nav-link" data-toggle-group="roles" title="Roles">
                        <span class="size-[18px] shrink-0" aria-hidden="true">⚿</span>
                        <span class="nav-label flex-1 text-left">Roles</span><span class="nav-chevron text-xs">⌄</span>
                    </button>
                    <div class="nav-group-content ml-[21px] hidden border-l border-white/10 py-1 pl-2" data-content="roles">
                        <a class="nav-child {{ request()->routeIs('roles.create') ? 'active' : '' }}" href="{{ route('roles.create') }}">＋ <span>Add role</span></a>
                        <a class="nav-child {{ request()->routeIs('roles.index', 'roles.show', 'roles.edit', 'roles.sample-*') ? 'active' : '' }}" href="{{ route('roles.index') }}">≡ <span>Manage roles</span></a>
                    </div>
                </div>

                <div class="nav-group" data-group="sectors">
                    <button class="nav-link" data-toggle-group="sectors" title="Sector">
                        <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 1 4 17.5zM4 17.5A2.5 2.5 0 0 1 6.5 15H20M8 7h8m-8 4h6"/></svg>
                        <span class="nav-label flex-1 text-left">Sector</span><span class="nav-chevron text-xs">⌄</span>
                    </button>
                    <div class="nav-group-content ml-[21px] hidden border-l border-white/10 py-1 pl-2" data-content="sectors">
                        <a class="nav-child {{ request()->routeIs('sectors.create') ? 'active' : '' }}" href="{{ route('sectors.create') }}">＋ <span>Add sector</span></a>
                        <a class="nav-child {{ request()->routeIs('sectors.index', 'sectors.show', 'sectors.edit', 'sectors.delete') ? 'active' : '' }}" href="{{ route('sectors.index') }}">≡ <span>Manage sectors</span></a>
                    </div>
                </div>
                <div class="nav-group" data-group="partners">
                    <button class="nav-link" data-toggle-group="partners" title="Development partner/Donor">
                        <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 1 4 17.5zM4 17.5A2.5 2.5 0 0 1 6.5 15H20M8 7h8m-8 4h6"/></svg>
                        <span class="nav-label flex-1 text-left">Development partner/Donor</span><span class="nav-chevron text-xs">⌄</span>
                    </button>
                    <div class="nav-group-content ml-[21px] hidden border-l border-white/10 py-1 pl-2" data-content="partners">
                        <a class="nav-child {{ request()->routeIs('partners.create') ? 'active' : '' }}" href="{{ route('partners.create') }}">＋ <span>Add partner/donor</span></a>
                        <a class="nav-child {{ request()->routeIs('partners.index', 'partners.show', 'partners.edit', 'partners.delete') ? 'active' : '' }}" href="{{ route('partners.index') }}">≡ <span>Manage partners/donors</span></a>
                    </div>
                </div>
                <a class="nav-link {{ request()->routeIs('logs.index') ? 'active' : '' }}" href="{{ route('logs.index') }}" title="Logs"><svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6M9 12h6M9 17h6"/></svg><span class="nav-label flex-1">Logs</span></a>
                            @endif
</nav>

            <div class="sidebar-foot flex items-center gap-3 border-t border-white/10 px-4 py-4">
                <div class="grid size-9 shrink-0 place-items-center rounded-full bg-[#20a99f] text-xs font-bold">{{ collect(explode(' ', auth()->user()?->name ?? (config('portfolio.enabled') ? 'Visitor' : 'Aina Khan')))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('') }}</div>
                <div class="profile-copy min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold">{{ auth()->user()?->name ?? (config('portfolio.enabled') ? 'Visitor' : 'Aina Khan') }}</p>
                    <p class="mt-0.5 text-[10px] text-teal-100/55">{{ $sidebarRole }}</p>
                </div>
                @include('partials.account-menu', ['placement' => 'above'])
            </div>
        </aside>

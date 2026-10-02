<details class="relative" data-account-menu>
    <summary class="icon-button cursor-pointer list-none" aria-label="Account menu" title="Account menu">⌄</summary>
    <div class="absolute right-0 z-50 w-44 rounded-lg border border-slate-200 bg-white p-2 text-sm text-ink shadow-lg {{ $placement === 'above' ? 'bottom-full mb-2' : 'top-full mt-2' }}">
        <a class="block rounded-lg px-3 py-2 hover:bg-slate-100" href="{{ route('account.edit') }}">Account</a>
        @auth
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button class="block w-full rounded-lg px-3 py-2 text-left hover:bg-slate-100">Logout</button>
            </form>
        @else
            <a class="block rounded-lg px-3 py-2 hover:bg-slate-100" href="{{ route('login') }}">Log in</a>
        @endauth
    </div>
</details>

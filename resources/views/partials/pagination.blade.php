@if($paginator->hasPages())
    @php
        $lastPage = $paginator->lastPage();
        $visiblePages = $lastPage > 5 ? collect([1, 2, $paginator->currentPage(), $lastPage - 1, $lastPage])->unique()->sort()->values()->all() : range(1, $lastPage);
        $pages = [];
        foreach ($visiblePages as $page) {
            if ($pages && $page > end($pages) + 1) {
                $pages[] = null;
            }
            $pages[] = $page;
        }
        $linkClass = 'inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border px-3 text-sm font-semibold';
    @endphp
    <nav class="flex flex-wrap items-center justify-between gap-3 py-4" aria-label="Pagination">
        <p class="text-sm text-slate-500">Page <strong class="text-ink">{{ $paginator->currentPage() }}</strong> of {{ $lastPage }}</p>
        <div class="flex flex-wrap items-center gap-1">
            @if($paginator->onFirstPage())
                <span class="{{ $linkClass }} border-slate-200 text-slate-400" aria-disabled="true" aria-label="Previous page">&larr;</span>
            @else
                <a class="{{ $linkClass }} border-teal/20 bg-white text-teal hover:bg-teal/10 focus-visible:outline-2 focus-visible:outline-teal" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">&larr;</a>
            @endif
            @foreach($pages as $page)
                @if($page === null)
                    <span class="px-1 text-slate-400" aria-hidden="true">&hellip;</span>
                @elseif($page === $paginator->currentPage())
                    <span class="{{ $linkClass }} border-teal bg-teal text-white" aria-current="page" aria-label="Page {{ $page }}">{{ $page }}</span>
                @else
                    <a class="{{ $linkClass }} border-teal/20 bg-white text-teal hover:bg-teal/10 focus-visible:outline-2 focus-visible:outline-teal" href="{{ $paginator->url($page) }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                @endif
            @endforeach
            @if($paginator->hasMorePages())
                <a class="{{ $linkClass }} border-teal/20 bg-white text-teal hover:bg-teal/10 focus-visible:outline-2 focus-visible:outline-teal" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">&rarr;</a>
            @else
                <span class="{{ $linkClass }} border-slate-200 text-slate-400" aria-disabled="true" aria-label="Next page">&rarr;</span>
            @endif
        </div>
    </nav>
@endif

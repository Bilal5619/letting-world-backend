@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-4 border-t border-line px-5 py-3 text-sm" aria-label="Pagination">
        <p class="text-muted">
            Showing <span class="font-medium text-ink">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
            of <span class="font-medium text-ink">{{ number_format($paginator->total()) }}</span>
        </p>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="lw-btn lw-btn-sm lw-btn-ghost opacity-40" aria-disabled="true"><x-admin.icon name="chevron-left" class="size-4" /></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="lw-btn lw-btn-sm lw-btn-ghost" aria-label="Previous page"><x-admin.icon name="chevron-left" class="size-4" /></a>
            @endif
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-muted">…</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="lw-btn lw-btn-sm min-w-8 bg-brand text-white dark:bg-accent dark:text-[#06122a]" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="lw-btn lw-btn-sm lw-btn-ghost min-w-8">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="lw-btn lw-btn-sm lw-btn-ghost" aria-label="Next page"><x-admin.icon name="chevron-right" class="size-4" /></a>
            @else
                <span class="lw-btn lw-btn-sm lw-btn-ghost opacity-40" aria-disabled="true"><x-admin.icon name="chevron-right" class="size-4" /></span>
            @endif
        </div>
    </nav>
@endif

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}"
         class="flex items-center justify-end gap-2 mono-meta">
        @if ($paginator->onFirstPage())
            <span class="chip opacity-40 cursor-not-allowed" aria-disabled="true">
                @lang('pagination.previous')
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="chip">
                @lang('pagination.previous')
            </a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="chip">
                @lang('pagination.next')
            </a>
        @else
            <span class="chip opacity-40 cursor-not-allowed" aria-disabled="true">
                @lang('pagination.next')
            </span>
        @endif
    </nav>
@endif

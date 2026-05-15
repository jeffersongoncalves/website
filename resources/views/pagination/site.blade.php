@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}"
         class="flex items-center justify-between flex-wrap gap-4 mono-meta">

        @if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            <div class="text-ink-500">
                <span class="text-ink-200">{{ $paginator->firstItem() ?? 0 }}</span>–<span class="text-ink-200">{{ $paginator->lastItem() ?? 0 }}</span>
                <span class="text-ink-500 mx-1">/</span>
                <span class="text-ink-200">{{ $paginator->total() }}</span>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-2">
            @if ($paginator->onFirstPage())
                <span class="chip opacity-40 cursor-not-allowed" aria-disabled="true">
                    @lang('pagination.previous')
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="chip">
                    @lang('pagination.previous')
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="chip opacity-40 cursor-default" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="chip chip-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="chip">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="chip">
                    @lang('pagination.next')
                </a>
            @else
                <span class="chip opacity-40 cursor-not-allowed" aria-disabled="true">
                    @lang('pagination.next')
                </span>
            @endif
        </div>
    </nav>
@endif

{{-- Livewire-driven twin of pagination/site.blade.php: identical chrome, but
     page changes go through wire:click (setPage/next/previous) so the list
     morphs in place instead of a full navigation. getPageName() keeps multiple
     paginators on one page (e.g. each /links section) independent. --}}
@if ($paginator->hasPages())
    {{-- On any page change, smooth-scroll back to the top of the enclosing
         <section> (each has scroll-margin-top) so paging doesn't leave the
         viewport stranded mid-list after the in-place morph. --}}
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}"
         x-data="{}"
         x-on:click="$el.closest('section')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
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
                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" rel="prev" class="chip">
                    @lang('pagination.previous')
                </button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="chip opacity-40 cursor-default" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="chip chip-active" aria-current="page" wire:key="paginator-{{ $paginator->getPageName() }}-page-{{ $page }}">{{ $page }}</span>
                        @else
                            <button type="button" wire:key="paginator-{{ $paginator->getPageName() }}-page-{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="chip">{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" rel="next" class="chip">
                    @lang('pagination.next')
                </button>
            @else
                <span class="chip opacity-40 cursor-not-allowed" aria-disabled="true">
                    @lang('pagination.next')
                </span>
            @endif
        </div>
    </nav>
@endif

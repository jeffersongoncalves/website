<div>
    @if($articles->isEmpty())
        <div class="text-center py-16 mono text-sm text-ink-500">@lang('site.articles.empty')</div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($articles as $project)
                <x-site.project-card :project="$project"/>
            @endforeach
        </div>

        <div class="mt-10">
            {{ $articles->onEachSide(1)->links() }}
        </div>
    @endif
</div>

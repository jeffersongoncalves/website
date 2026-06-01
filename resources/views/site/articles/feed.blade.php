<?php echo '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
<title>{{ __('site.articles.title') }} — Jefferson Gonçalves</title>
<link>{{ route('articles.index') }}</link>
<description>{{ __('site.articles.sub') }}</description>
<language>{{ str_replace('_', '-', app()->getLocale()) }}</language>
<atom:link href="{{ route('articles.feed') }}" rel="self" type="application/rss+xml"/>
@foreach($articles as $article)
@php($locale = \App\Support\LocaleSupport::short())
@php($description = $article->getTranslation('title', $locale, false) ?: $article->name)
<item>
<title>{{ $article->name }}</title>
<link>{{ route('projects.show', ['slug' => $article->slug]) }}</link>
<guid isPermaLink="true">{{ route('projects.show', ['slug' => $article->slug]) }}</guid>
@if($article->published_at)<pubDate>{{ $article->published_at->toRssString() }}</pubDate>@endif
<description>{{ $description }}</description>
</item>
@endforeach
</channel>
</rss>

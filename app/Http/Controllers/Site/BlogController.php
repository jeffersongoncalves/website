<?php

namespace App\Http\Controllers\Site;

use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BlogController
{
    public function index(Request $request): View
    {
        $activeTag = $request->string('tag')->toString() ?: 'all';

        $query = Post::query()->published()->orderByDesc('published_at');

        if ($activeTag !== 'all') {
            $query->whereJsonContains('tags', $activeTag);
        }

        $posts = $query->get();

        $tags = Post::query()
            ->published()
            ->pluck('tags')
            ->filter()
            ->flatten()
            ->unique()
            ->values()
            ->all();

        return view('site.blog.index', compact('posts', 'tags', 'activeTag'));
    }

    public function show(string $locale, string $slug): View
    {
        $post = Post::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $related = Post::query()
            ->published()
            ->where('id', '!=', $post->id)
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        return view('site.blog.show', compact('post', 'related'));
    }
}

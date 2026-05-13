<?php

namespace App\Observers;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;

class PostObserver
{
    public function created(Post $post): void
    {
        $this->flush();
    }

    public function updated(Post $post): void
    {
        $this->flush();
    }

    public function deleted(Post $post): void
    {
        $this->flush();
    }

    public function restored(Post $post): void
    {
        $this->flush();
    }

    public function forceDeleted(Post $post): void
    {
        $this->flush();
    }

    private function flush(): void
    {
        try {
            Cache::delete('posts_count');
        } catch (InvalidArgumentException) {
        }
    }
}

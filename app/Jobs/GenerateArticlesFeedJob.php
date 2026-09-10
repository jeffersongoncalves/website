<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Support\Facades\Artisan;

class GenerateArticlesFeedJob extends DebouncedJob
{
    public function uniqueId(): string
    {
        return 'articles-feed:generate';
    }

    public function handle(): void
    {
        Artisan::call('articles-feed:generate');
    }
}

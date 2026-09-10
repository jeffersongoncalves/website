<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Support\Facades\Artisan;

class GenerateSitemapJob extends DebouncedJob
{
    public function uniqueId(): string
    {
        return 'sitemap:generate';
    }

    public function handle(): void
    {
        Artisan::call('sitemap:generate');
    }
}

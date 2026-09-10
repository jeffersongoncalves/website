<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Support\Facades\Artisan;

class GenerateLlmsTxtJob extends DebouncedJob
{
    public function uniqueId(): string
    {
        return 'llms:generate';
    }

    public function handle(): void
    {
        Artisan::call('llms:generate');
    }
}

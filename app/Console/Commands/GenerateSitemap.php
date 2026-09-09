<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\SitemapGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Write sitemap.xml to storage/app (survives deploy release swaps, unlike public/)';

    public function handle(): int
    {
        Storage::disk('local')->put('sitemap.xml', SitemapGenerator::build());

        $this->info('Wrote sitemap.xml to storage');

        return self::SUCCESS;
    }
}

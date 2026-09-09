<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ArticlesFeedGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateArticlesFeed extends Command
{
    protected $signature = 'articles-feed:generate';

    protected $description = 'Write articles-feed-{locale}.xml to storage/app (survives deploy release swaps, unlike public/)';

    public function handle(): int
    {
        foreach (ArticlesFeedGenerator::buildAll() as $locale => $xml) {
            Storage::disk('local')->put(ArticlesFeedGenerator::storageKey($locale), $xml);
        }

        $this->info('Wrote articles-feed-{locale}.xml to storage');

        return self::SUCCESS;
    }
}

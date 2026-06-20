<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\MigrateBlogSitesToArticlesJob;
use Illuminate\Console\Command;

class MigrateBlogSitesToArticles extends Command
{
    protected $signature = 'projects:migrate-blog-sites';

    protected $description = 'Reclassify Website projects whose docs_url path looks like a blog post as Articles';

    public function handle(): int
    {
        // Run the job inline and report its converted count. (The job's
        // WithoutOverlapping middleware only applies on the queued path used by
        // the one-time operation; an ad-hoc command run is deliberate.)
        $count = (new MigrateBlogSitesToArticlesJob)->handle();

        $this->info("Reclassified {$count} website(s) as articles.");

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\LlmsTxtGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateLlmsTxt extends Command
{
    protected $signature = 'llms:generate';

    protected $description = 'Write llms.txt to storage/app (survives deploy release swaps, unlike public/)';

    public function handle(): int
    {
        Storage::disk('local')->put('llms.txt', LlmsTxtGenerator::build());

        $this->info('Wrote llms.txt to storage');

        return self::SUCCESS;
    }
}

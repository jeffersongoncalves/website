<?php

namespace App\Jobs;

use App\Models\Project;
use App\Support\GeminiTranslate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Translate the project's English title into pt-BR and es by prompting
 * Gemini 2.5 Flash through Prism. Throttled globally via `Redis::throttle`
 * so a batch import doesn't flood the Gemini API and trip per-key rate
 * limits.
 */
class TranslateProjectTitleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Stored locale → human-readable name fed into the translation prompt.
     *
     * @var array<string, string>
     */
    private const TARGETS = [
        'pt' => 'Brazilian Portuguese',
        'es' => 'Spanish',
    ];

    public function __construct(public Project $project)
    {
        $this->onQueue('translations');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("project-translate:{$this->project->getKey()}"))->dontRelease()];
    }

    public function handle(): void
    {
        Redis::throttle('gemini-translate')
            ->block(0)
            ->allow(30)
            ->every(60)
            ->then(
                fn () => $this->translate(),
                fn () => $this->release(30),
            );
    }

    private function translate(): void
    {
        $project = $this->project->refresh();

        $source = is_string($project->getTranslation('title', 'en', false) ?: null)
            ? (string) $project->getTranslation('title', 'en', false)
            : '';

        if (trim($source) === '') {
            return;
        }

        $changed = false;

        foreach (self::TARGETS as $localeKey => $targetLanguage) {
            $current = $project->getTranslation('title', $localeKey, false);

            // Preserve manual translations; only overwrite when the locale
            // is empty or still mirrors the English source (importer default).
            if (is_string($current) && trim($current) !== '' && $current !== $source) {
                continue;
            }

            try {
                $translated = GeminiTranslate::translate($source, $targetLanguage);
            } catch (Throwable $e) {
                Log::warning('TranslateProjectTitleJob: translate call threw', [
                    'project' => $project->slug,
                    'locale' => $localeKey,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if (! is_string($translated) || trim($translated) === '') {
                continue;
            }

            $project->setTranslation('title', $localeKey, $translated);
            $changed = true;
        }

        if ($changed) {
            $project->saveQuietly();
        }
    }
}

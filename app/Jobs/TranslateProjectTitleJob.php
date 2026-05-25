<?php

namespace App\Jobs;

use App\Enums\ProjectCategory;
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

    // Each rate-limited release counts as an attempt, so the ceiling has
    // to absorb a long queue (~hundred jobs throttled to 15/min). 25 tries
    // × 30s release ≈ 12.5 min of patience before a job is marked failed.
    public int $tries = 25;

    public int $backoff = 60;

    // Don't let a sustained Gemini outage poison the whole queue with
    // permanent failures — give the job two real exception cycles before
    // burning the remaining tries on retry attempts.
    public int $maxExceptions = 2;

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
        // Gemini's free-tier ceiling for 2.5-flash is 15 RPM; the throttle
        // matches that so a paid-tier upgrade is the only thing we need
        // to touch when it eventually happens. block(5) lets the worker
        // wait up to 5s for a slot before releasing, which smooths short
        // bursts without burning a try.
        Redis::throttle('gemini-translate')
            ->block(5)
            ->allow(15)
            ->every(60)
            ->then(
                fn () => $this->translate(),
                fn () => $this->release(15),
            );
    }

    private function translate(): void
    {
        $project = $this->project->refresh();

        // YouTube channels and external sites carry the channel/owner/site
        // name verbatim in the title — translating proper nouns turns
        // "Akitando" or "Beyond Code" into garbage, so skip these
        // categories entirely.
        if (in_array($project->category, [ProjectCategory::Website, ProjectCategory::YoutubeChannel], true)) {
            return;
        }

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

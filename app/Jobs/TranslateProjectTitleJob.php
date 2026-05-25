<?php

namespace App\Jobs;

use App\Models\Project;
use App\Support\GoogleTranslate;
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
 * Translate the project's English title into pt-BR and es using the public
 * Google Translate endpoint. The job is rate-limited globally via
 * `Redis::throttle` so we don't fan out concurrent requests to Google when
 * many projects are imported in a single batch — the endpoint is
 * undocumented and aggressive throttling on Google's side will start
 * returning 429s if we burst.
 */
class TranslateProjectTitleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Google's tl code per stored locale. The DB column uses the bare 'pt'
     * key but we request 'pt-BR' from Google so the output is Brazilian.
     *
     * @var array<string, string>
     */
    private const TARGETS = [
        'pt' => 'pt-BR',
        'es' => 'es',
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
        Redis::throttle('google-translate')
            ->block(0)
            ->allow(20)
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

        foreach (self::TARGETS as $localeKey => $googleCode) {
            $current = $project->getTranslation('title', $localeKey, false);

            // Don't overwrite a manual translation, and don't re-translate
            // when the existing value is already different from the source
            // (means the editor either translated it or the importer
            // populated a per-locale value already).
            if (is_string($current) && trim($current) !== '' && $current !== $source) {
                continue;
            }

            try {
                $translated = GoogleTranslate::translate($source, $googleCode, 'en');
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

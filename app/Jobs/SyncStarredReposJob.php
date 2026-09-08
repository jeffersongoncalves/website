<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JeffersonGoncalves\GitHubClient\Exceptions\GitHubRateLimitException;

/**
 * Page the GitHub account's starred repos newest-first and dispatch an
 * ImportStarredRepoJob for every star newer than the high-water mark.
 *
 * The cursor is `MAX(projects.starred_at)` — no separate marker table, so it
 * survives the cache flush the prod entrypoint runs on every deploy. Because
 * the list is sorted desc, the first item at/below the cursor means everything
 * after it is already known, so pagination stops there. Idempotent: a second
 * run finds nothing newer and dispatches nothing.
 */
class SyncStarredReposJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $backoff = 30;

    // 0 = unlimited attempts; retryUntil() bounds the retries by time. Avoids
    // MaxAttemptsExceededException when GitHub rate-limit releases pile up.
    public int $tries = 0;

    private const EPOCH = '1970-01-01T00:00:00Z';

    private const PER_PAGE = 100;

    public function __construct(public bool $full = false)
    {
        $this->onQueue('github');
    }

    /**
     * Time-based retries so GitHub rate-limit releases don't exhaust a fixed
     * attempt budget — see SyncProjectMetricsJob::retryUntil for the rationale.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(2);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping('sync-starred')->dontRelease(),
            new RateLimited('github-api'),
        ];
    }

    public function handle(): void
    {
        $username = config('services.github.username');

        if (! is_string($username) || $username === '') {
            Log::warning('SyncStarredReposJob skipped: services.github.username not set');

            return;
        }

        // max() is an aggregate — it returns the raw DB scalar, not a cast
        // Carbon. ImportStarredRepoJob stores starred_at in UTC, so read it back
        // as UTC (NOT app.timezone) and format to the same Z-suffixed ISO the API
        // emits. Parsing without an explicit zone would apply the app offset and
        // skip stars inside that window.
        $max = Project::max('starred_at');
        $cursor = ($this->full || $max === null)
            ? self::EPOCH
            : CarbonImmutable::parse($max, 'UTC')->format('Y-m-d\TH:i:s\Z');

        try {
            $dispatched = $this->paginate($username, $cursor);
        } catch (GitHubRateLimitException $e) {
            // Window won't clear until it resets — release with a delay instead
            // of burning attempts that would all 403 in turn.
            $this->release($e->retryAfter);

            return;
        }

        Log::info('SyncStarredReposJob done', [
            'cursor' => $cursor,
            'dispatched' => $dispatched,
            'full' => $this->full,
        ]);
    }

    public function failed(?\Throwable $e): void
    {
        Log::error('SyncStarredReposJob failed', [
            'full' => $this->full,
            'error' => $e?->getMessage(),
        ]);
    }

    /**
     * @throws GitHubRateLimitException|ConnectionException
     */
    private function paginate(string $username, string $cursor): int
    {
        $dispatched = 0;

        for ($page = 1; ; $page++) {
            $response = Http::timeout(15)
                ->withHeaders($this->headers())
                ->get("https://api.github.com/users/{$username}/starred", [
                    'sort' => 'created',
                    'direction' => 'desc',
                    'per_page' => self::PER_PAGE,
                    'page' => $page,
                ]);

            $this->throwIfRateLimited($response);

            if (! $response->successful()) {
                Log::warning('SyncStarredReposJob: GitHub returned an error', [
                    'status' => $response->status(),
                    'page' => $page,
                ]);

                return $dispatched;
            }

            $items = $response->json();

            if (! is_array($items) || $items === []) {
                return $dispatched;
            }

            foreach ($items as $item) {
                $starredAt = is_array($item) ? ($item['starred_at'] ?? null) : null;
                $htmlUrl = is_array($item) ? ($item['repo']['html_url'] ?? null) : null;

                if (! is_string($starredAt) || ! is_string($htmlUrl)) {
                    continue;
                }

                // ISO 8601 is lexicographically ordered, so a plain string
                // compare gives chronological order. Desc list + this stop
                // condition means everything past here is already known.
                if ($starredAt <= $cursor) {
                    return $dispatched;
                }

                // Staggered by 1s/job (matches the shared 'github-api' 60/min
                // limiter) instead of dumping the whole page onto the queue at
                // once. A --full resync can dispatch thousands in one pass —
                // without this, they all become ready simultaneously and
                // thunder-herd the RateLimited middleware (grab → over-limit →
                // release, repeated across the whole backlog every ~60s)
                // instead of draining smoothly.
                ImportStarredRepoJob::dispatch($htmlUrl, $starredAt)->delay(now()->addSeconds($dispatched));
                $dispatched++;
            }

            // Short page = last page.
            if (count($items) < self::PER_PAGE) {
                return $dispatched;
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [
            'User-Agent' => 'jeffersongoncalves-site',
            // star+json turns each item into { starred_at, repo } instead of a
            // bare repo object — without it there is no star timestamp.
            'Accept' => 'application/vnd.github.star+json',
        ];

        if ($token = config('services.github.token')) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $headers;
    }

    /**
     * Mirror of ProjectMetrics::throwIfRateLimited — primary limit (403 +
     * X-RateLimit-Remaining: 0) and secondary/abuse limit (403/429 with
     * Retry-After) both abort the whole pass.
     *
     * @throws GitHubRateLimitException
     */
    private function throwIfRateLimited(Response $response): void
    {
        $status = $response->status();

        if ($status !== 403 && $status !== 429) {
            return;
        }

        $retryAfterHeader = $response->header('Retry-After');
        $remaining = $response->header('X-RateLimit-Remaining');

        if ($remaining !== '0' && $retryAfterHeader === '') {
            return;
        }

        if ($retryAfterHeader !== '') {
            $retryAfter = (int) $retryAfterHeader;
        } else {
            $retryAfter = ((int) $response->header('X-RateLimit-Reset')) - time();
        }

        throw new GitHubRateLimitException(max(60, $retryAfter));
    }
}

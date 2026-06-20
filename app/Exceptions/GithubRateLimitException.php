<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when GitHub answers a core-API call with a rate-limit response
 * (primary 403 with `X-RateLimit-Remaining: 0`, or a secondary 403/429 that
 * carries a `Retry-After`). The dispatching job catches this and releases
 * itself back to the queue with `$retryAfter` seconds of delay instead of
 * burning attempts against a limit that won't clear until the window resets.
 */
class GithubRateLimitException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct("GitHub API rate limit hit; retry in {$retryAfter}s");
    }
}

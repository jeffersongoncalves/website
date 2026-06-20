<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

abstract class AppVersion
{
    /**
     * Current application version, derived from (in order):
     *   1. APP_VERSION env / config
     *   2. public/VERSION file written at deploy time
     *   3. `git describe --tags --abbrev=0` (dev only, .git available)
     *   4. fallback `0.0.0`
     *
     * When the env path resolves (production sets APP_VERSION at deploy time)
     * the lookup is O(1) and we deliberately skip the cache. Earlier versions
     * cached the value indefinitely in Redis, which meant a fresh deploy still
     * served the previous version for up to an hour after rollout. Only the
     * slow paths (VERSION file I/O + `git describe` shell-out) are still cached.
     */
    public static function current(): string
    {
        $configured = config('app.version');

        if (is_string($configured) && $configured !== '') {
            return self::normalize($configured);
        }

        return Cache::remember('app.version', 3600, function (): string {
            $file = base_path('VERSION');
            if (is_file($file)) {
                $raw = trim((string) file_get_contents($file));
                if ($raw !== '') {
                    return self::normalize($raw);
                }
            }

            if (is_dir(base_path('.git')) && function_exists('exec')) {
                $output = [];
                $code = null;
                @exec('git -C '.escapeshellarg(base_path()).' describe --tags --abbrev=0 2>'.(PHP_OS_FAMILY === 'Windows' ? 'nul' : '/dev/null'), $output, $code);
                if ($code === 0 && isset($output[0]) && $output[0] !== '') {
                    return self::normalize($output[0]);
                }
            }

            return '0.0.0';
        });
    }

    /**
     * Strip the `release-` prefix and any leading `v` so the result is the
     * bare semver string (e.g. `release-1.0.24` → `1.0.24`).
     */
    private static function normalize(string $value): string
    {
        $value = preg_replace('/^release-/', '', $value) ?? $value;
        $value = ltrim($value, 'vV');

        return $value;
    }
}

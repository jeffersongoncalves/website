<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalise + merge raw topic/keyword lists coming from GitHub topics,
 * composer.json `keywords`, package.json `keywords` and Packagist keywords
 * into one clean, deduplicated, capped list stored on the project.
 */
class ProjectTopics
{
    /**
     * Hard cap so a repo with dozens of keywords doesn't bloat the row /
     * the catalogue UI.
     */
    private const MAX = 20;

    /**
     * @param  array<int, mixed>  ...$lists
     * @return list<string>
     */
    public static function normalize(array ...$lists): array
    {
        $out = [];

        foreach ($lists as $list) {
            foreach ($list as $raw) {
                if (! is_string($raw)) {
                    continue;
                }

                $topic = Str::slug(trim($raw));

                // Drop empties and implausible values (junk, overly long).
                if ($topic === '' || strlen($topic) > 50) {
                    continue;
                }

                $out[$topic] = true;

                if (count($out) >= self::MAX) {
                    break 2;
                }
            }
        }

        return array_keys($out);
    }
}

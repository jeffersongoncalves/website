<?php

declare(strict_types=1);

namespace App\Support;

class ProjectAttributes
{
    public static function prettifyName(string $value): string
    {
        $cleaned = str_replace(['@', '/'], ['', ' '], $value);
        $cleaned = str_replace(['-', '_', '.'], ' ', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned;
        $cleaned = ucwords(trim($cleaned));

        return preg_replace('/\bPhp\b/u', 'PHP', $cleaned) ?? $cleaned;
    }

    /**
     * Flatten dotted translatable keys (`title.pt`, ...) back into the nested
     * array shape that HasTranslations expects on mass assignment.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function normalize(array $fields): array
    {
        $attributes = [];

        foreach ($fields as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if (str_contains($key, '.')) {
                [$column, $locale] = explode('.', $key, 2);
                $attributes[$column][$locale] = $value;

                continue;
            }

            $attributes[$key] = $value;
        }

        return $attributes;
    }
}

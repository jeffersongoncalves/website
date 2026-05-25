<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin wrapper around Google's unauthenticated `translate.googleapis.com`
 * endpoint. The endpoint is intended for the official Google Translate
 * client but it accepts public requests with no API key — great for
 * one-off catalogue translations, not great for high-volume production
 * use (the endpoint is rate-limited and undocumented; if Google tightens
 * access this class is the only place that needs to switch to the paid
 * Cloud Translation API).
 *
 * Response shape (truncated):
 *   [
 *       [
 *           ["translated chunk", "source chunk", null, null, 1],
 *           ...
 *       ],
 *       null,
 *       "pt",   // detected source language
 *       ...
 *   ]
 */
abstract class GoogleTranslate
{
    private const ENDPOINT = 'https://translate.googleapis.com/translate_a/single';

    /**
     * Translate a string. Returns null when the network call fails or the
     * response shape doesn't match — callers should fall back to the
     * original text rather than persisting null.
     */
    public static function translate(string $text, string $target, string $source = 'auto'): ?string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; jeffersongoncalves-site)',
                    'Accept' => 'application/json',
                ])
                ->get(self::ENDPOINT, [
                    'client' => 'gtx',
                    'sl' => $source,
                    'tl' => $target,
                    'dt' => 't',
                    'q' => $text,
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->json();

        if (! is_array($body) || ! is_array($body[0] ?? null)) {
            return null;
        }

        // Each chunk is itself a list, where index 0 is the translated piece.
        // Concatenate them so multi-sentence inputs come back as one string.
        $output = '';
        foreach ($body[0] as $chunk) {
            if (is_array($chunk) && isset($chunk[0]) && is_string($chunk[0])) {
                $output .= $chunk[0];
            }
        }

        return $output === '' ? null : $output;
    }
}

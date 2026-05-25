<?php

namespace App\Support;

use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Throwable;

/**
 * Translate short catalogue strings (project titles, blurbs) by prompting
 * Gemini 2.5 Flash through Prism. The model is forced into a "translate
 * only — no commentary" mode so the response is safe to drop straight
 * into a UI label. Returns null on any failure so callers can fall back
 * to the source text rather than overwrite with garbage.
 *
 * Requires `GEMINI_API_KEY` in the environment.
 */
abstract class GeminiTranslate
{
    private const MODEL = 'gemini-2.5-flash';

    /**
     * @param  string  $target  Human-readable target language (e.g. "Brazilian Portuguese", "Spanish").
     *                          The prompt steers the model toward the requested locale; using a
     *                          natural-language label lets us share one model across en→pt-BR
     *                          and en→es without juggling locale codes inside the prompt.
     */
    public static function translate(string $text, string $target): ?string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $prompt = sprintf(
            'Translate the following text into %s. Brand names and technical product names (Laravel, Filament, Livewire, Tailwind, Alpine.js, npm, GitHub, Docker, Composer, etc.) MUST stay in English. Reply with the translation only — no quotes, no preamble, no trailing punctuation that the source did not already have. Text: "%s"',
            $target,
            $text,
        );

        try {
            $response = Prism::text()
                ->using(Provider::Gemini, self::MODEL)
                ->withPrompt($prompt)
                ->asText();
        } catch (Throwable) {
            return null;
        }

        $output = trim((string) $response->text);

        // Trim balanced wrapping quotes — the model occasionally returns the
        // translation surrounded by `"..."` despite the instruction.
        $output = preg_replace('/^["\']|["\']$/u', '', $output) ?? $output;

        return $output === '' ? null : $output;
    }
}

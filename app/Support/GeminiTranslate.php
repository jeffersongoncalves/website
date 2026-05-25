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
     * Brand / product names the model insists on localising even when the
     * prompt forbids it. Order matters — longer phrases (Flux Pro, Laravel
     * Nova) must come before their substrings so `strtr` doesn't run a
     * shorter mapping over a longer one and split it.
     *
     * @var array<string, string>
     */
    private const BRAND_GLOSSARY = [
        'Fluxo Pro' => 'Flux Pro',
        'fluxo pro' => 'Flux Pro',
        'Flujo Pro' => 'Flux Pro',
        'flujo pro' => 'Flux Pro',
        'Laravel Nova' => 'Laravel Nova',
        'Filamento' => 'Filament',
        'filamento' => 'Filament',
        'Fio Vivo' => 'Livewire',
        'fio vivo' => 'Livewire',
        'Cabo Vivo' => 'Livewire',
        'cabo vivo' => 'Livewire',
        'Filament Nativo' => 'Filament Native',
        'Filament Móvel' => 'Filament Mobile',
        'Filament Móvil' => 'Filament Mobile',
        'React Nativo' => 'React Native',
        'React Móvil' => 'React Native',
        'Lâmina' => 'Blade',
        'lâmina' => 'Blade',
        'Chama' => 'Blaze',
        'chama' => 'Blaze',
        'Llama' => 'Blaze',
        'llama' => 'Blaze',
        'Resplandor' => 'Blaze',
        'resplandor' => 'Blaze',
    ];

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
            "Translate the following text into %s.\n".
            "STRICT RULES:\n".
            "1. The following brand and product names MUST stay in English exactly as written, never translated or transliterated: Laravel, Laravel Nova, Filament, Filament Native, Filament Mobile, Livewire, Volt, Flux, Flux Pro, Blaze, Blade, Native, Mobile, React, React Native, Vue, Svelte, Inertia, Tailwind, Tailwind CSS, Alpine, Alpine.js, Pest, PHPUnit, PHPStan, Composer, npm, pnpm, Vite, GitHub, GitLab, Bitbucket, Docker, Packagist, JetBrains, PhpStorm, VS Code, CakePHP, Symfony, Redis, MySQL, PostgreSQL, MariaDB, SQLite, MongoDB, Elasticsearch, Meilisearch, Typesense, Horizon, Pulse, Reverb, Octane, Sail, Forge, Vapor, Sanctum, Passport, Socialite, Scout, Telescope, Traefik, Nginx, Apache, Caddy, Kubernetes, Helm, Ansible, Terraform.\n".
            "2. Reply with the translation only — no quotes, no preamble, no trailing punctuation that the source did not already have.\n".
            'Text: "%s"',
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
        $output = self::applyBrandGlossary($output);

        return $output === '' ? null : $output;
    }

    /**
     * Post-processing fallback for the brand names the model keeps
     * translating despite the prompt. Public so the cleanup operation
     * (and any future Filament action) can re-run it against existing
     * rows without re-hitting Gemini.
     */
    public static function applyBrandGlossary(string $text): string
    {
        return strtr($text, self::BRAND_GLOSSARY);
    }
}

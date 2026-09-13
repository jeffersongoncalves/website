<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use RalphJSmit\Laravel\SEO\SEOManager;
use RalphJSmit\Laravel\SEO\Support\SEOData;

/**
 * AppServiceProvider::configureSeo()'s locale-fallback branch
 * (`if (empty($data->locale)) { $data->locale = match(...) }`) is
 * unreachable through a real request: RalphJSmit\Laravel\SEO\Support\SEOData's
 * own constructor unconditionally defaults `locale` to `app()->getLocale()`
 * when null is passed, so by the time any SEODataTransformer sees the object,
 * `$data->locale` is already a real (non-empty) value — never the raw 2-letter
 * app locale our match() expects to translate into "en_US"/"fr_FR"/etc. This
 * app has no code path that constructs a SEOData with a genuinely null
 * locale, so the fallback the docblock describes is currently dead code.
 *
 * Exercised directly here instead: pull the actual registered transformer off
 * SEOManager (the same instance AppServiceProvider::boot() registered onto)
 * and invoke it against a SEOData whose `locale` has been forced back to null
 * after construction — the one state the branch is written to handle.
 */
it('configureSeo\'s SEODataTransformer fills og:locale from the app locale when SEOData has none', function (string $appLocale, string $expected): void {
    App::setLocale($appLocale);

    $data = new SEOData;
    $data->locale = null; // force the state AppServiceProvider's empty() check guards for

    $transformers = app(SEOManager::class)->getSEODataTransformers();
    expect($transformers)->not->toBeEmpty();

    foreach ($transformers as $transformer) {
        $data = $transformer($data);
    }

    expect($data->locale)->toBe($expected);
})->with([
    ['en', 'en_US'],
    ['es', 'es_ES'],
    ['fr', 'fr_FR'],
    ['de', 'de_DE'],
    ['pt_BR', 'pt_BR'], // also exercises the match's default arm (short() -> 'pt')
]);

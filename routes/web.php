<?php

declare(strict_types=1);

use App\Http\Controllers\Site\ArticlesFeedController;
use App\Http\Controllers\Site\LlmsTxtController;
use App\Http\Controllers\Site\OfflineController;
use App\Http\Controllers\Site\OgImageController;
use App\Http\Controllers\Site\ReadmeImageController;
use App\Http\Controllers\Site\SitemapController;
use App\Livewire\Site\AboutPage;
use App\Livewire\Site\ArticlesPage;
use App\Livewire\Site\HomePage;
use App\Livewire\Site\LinksPage;
use App\Livewire\Site\McpGuidePage;
use App\Livewire\Site\OpenSourcePage;
use App\Livewire\Site\ProjectShowPage;
use App\Livewire\Site\ProjectsPage;
use App\Livewire\Site\SponsorsPage;
use App\Livewire\Site\StackPage;
use Daikazu\BladeWind\Http\InjectPageStyles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\PageCache\Middleware\CachePublicPage;
use JeffersonGoncalves\SecurityHeaders\Middleware\SecurityHeaders;

// Root-level routes (no locale prefix). Still wrapped in SecurityHeaders so
// these endpoints — especially /og and /favicon-proxy, which relay externally
// sourced bytes — get X-Content-Type-Options: nosniff and the rest.
Route::middleware([SecurityHeaders::class])->group(function () {
    // `/offline` is pre-cached by the service worker and served as the fallback
    // for navigation failures. The `/sw.js` route itself is registered by
    // jeffersongoncalves/laravel-pwa-service-worker (config pwa-service-worker.*),
    // with SecurityHeaders attached via that package's `middleware` config.
    Route::get('/offline', OfflineController::class)->name('pwa.offline');

    // Cached social-card proxy. Outside the locale/page-cache group: it's a
    // binary response and the image is locale-independent.
    Route::get('/og/{slug}.png', OgImageController::class)->name('og.show')
        ->middleware('throttle:60,1');

    // Cached README-image proxy — see ReadmeImageCache's docblock (GitHub's
    // raw-content CDN measured a 54s LCP on a hotlinked banner image).
    //
    // Unlike og.show (1 request/page), a single README can embed hundreds of
    // images (e.g. a big contributor-avatar grid) — all requested by one
    // visitor's browser on one page load. A 60/min per-IP cap was tripping
    // real visitors on those READMEs (every avatar past the 60th got a 429),
    // not abuse — bumped to comfortably cover a heavy single page load.
    Route::get('/readme-image/{encoded}', ReadmeImageController::class)->name('readme-image.show')
        ->middleware('throttle:600,1');

    // /favicon-proxy (name `favicon-proxy`) is registered by
    // jeffersongoncalves/laravel-favicon-proxy (config favicon-proxy.*).

    // llms.txt — plain-text site map for LLM crawlers (llmstxt.org). Lives at
    // the site root with no locale prefix; it's its own cached text body.
    Route::get('/llms.txt', LlmsTxtController::class)->name('llms');

    // /sitemap.xml — cached + DB-driven (see SitemapController), not a static
    // file, so it survives atomic deploys that swap the public/ directory.
    Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
});

// Every page below lives under /{locale} (e.g. /pt_BR/about, /en/projects) —
// there is no bare, unprefixed content route. `/` itself and any legacy
// unprefixed path (pre-i18n indexed URLs) are redirect-only, handled further
// down instead of served directly.
$localePattern = implode('|', array_map(
    static fn (string $locale): string => preg_quote($locale, '/'),
    (array) config('locale-cookie.supported', ['en']),
));

Route::prefix('{locale}')
    ->where(['locale' => $localePattern])
    // InjectPageStyles also runs globally, but that pass sits outside CachePublicPage, so the
    // cache would store BladeWind's placeholder links and a hit (which renders no views) would
    // rebuild the page CSS from the HTML alone, missing classes Livewire adds on update.
    // Running it inside the cache means the stored page already carries its page stylesheet.
    ->middleware([SecurityHeaders::class, 'set.locale', CachePublicPage::class, InjectPageStyles::class])
    ->group(function () {
        Route::get('/', HomePage::class)->name('home');
        Route::get('/about', AboutPage::class)->name('about');

        Route::get('/projects', ProjectsPage::class)->name('projects.index')
            ->withoutMiddleware(CachePublicPage::class);
        Route::get('/projects/{slug}', ProjectShowPage::class)->name('projects.show');

        Route::get('/articles', ArticlesPage::class)->name('articles.index')
            ->withoutMiddleware(CachePublicPage::class);
        Route::get('/articles/feed', ArticlesFeedController::class)->name('articles.feed')
            ->middleware('throttle:60,1');
        // Articles are Project rows but live under /articles/{slug} so the Articles
        // nav highlights instead of Projects. ProjectShowPage 301s any project to
        // its canonical section. Registered after /articles/feed so the static
        // segment still wins.
        Route::get('/articles/{slug}', ProjectShowPage::class)->name('articles.show');

        Route::get('/links', LinksPage::class)->name('links.index')
            ->withoutMiddleware(CachePublicPage::class);
        // External-link projects (sites, channels, learning resources, awesome
        // lists) are canonical under /links/{slug}; ProjectShowPage 301s any served
        // under the wrong section. Registered after the static /links route.
        Route::get('/links/{slug}', ProjectShowPage::class)->name('links.show');

        Route::get('/open-source', OpenSourcePage::class)->name('open-source');

        Route::get('/stack', StackPage::class)->name('stack');

        Route::get('/sponsors', SponsorsPage::class)->name('sponsors');

        // /developers/mcp — kept out of /mcp itself, which is the machine
        // endpoint (Mcp::web in routes/ai.php already owns GET/POST/DELETE there).
        Route::get('/developers/mcp', McpGuidePage::class)->name('developers.mcp');
    });

// Bare `/`: nothing above matches an empty path. Send the visitor to their
// cookie's locale, or `en` when there is none/unsupported — 302 because the
// target depends on a per-visitor cookie, never cacheable.
Route::get('/', function (Request $request) {
    $supported = (array) config('locale-cookie.supported', ['en']);
    $locale = $request->cookies->get(config('locale-cookie.cookie', 'locale'));
    $locale = in_array($locale, $supported, true) ? $locale : 'en';

    return redirect('/'.$locale);
})->middleware(SecurityHeaders::class);

// Legacy pre-i18n indexed URLs (/about, /projects/{slug}, ...) — 301 them onto
// their locale-prefixed equivalent so existing backlinks/search results keep
// working. Deliberately explicit routes (not a `/{any}` wildcard or
// Route::fallback()): jeffersongoncalves/laravel-short-url already registers
// its own Route::fallback() for root-level short codes (e.g. /abc123), and
// only one fallback route is ever reachable — a generic catch-all here would
// either lose to it or silently swallow every short-code request instead.
$legacyRedirect = function (Request $request, string $suffix): RedirectResponse {
    $supported = (array) config('locale-cookie.supported', ['en']);
    $locale = $request->cookies->get(config('locale-cookie.cookie', 'locale'));
    $locale = in_array($locale, $supported, true) ? $locale : 'en';
    $query = $request->getQueryString();

    return redirect('/'.$locale.'/'.$suffix.($query ? '?'.$query : ''), 301);
};

foreach ([
    'about', 'projects', 'articles', 'articles/feed', 'links',
    'open-source', 'stack', 'sponsors', 'developers/mcp',
] as $legacyPath) {
    Route::get('/'.$legacyPath, fn (Request $r) => $legacyRedirect($r, $legacyPath))
        ->middleware(SecurityHeaders::class);
}

foreach (['projects', 'articles', 'links'] as $section) {
    Route::get("/{$section}/{slug}", fn (Request $r, string $slug) => $legacyRedirect($r, "{$section}/{$slug}"))
        ->middleware(SecurityHeaders::class);
}

// /locale/{locale} (name `locale.switch`) is registered by
// jeffersongoncalves/laravel-locale-cookie (config locale-cookie.switch).

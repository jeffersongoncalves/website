<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\LocaleSupport;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use JeffersonGoncalves\LocaleCookie\Middleware\SetLocale;
use JeffersonGoncalves\Markdown\Markdown;
use Livewire\Livewire;
use RalphJSmit\Laravel\SEO\Facades\SEOManager;
use RalphJSmit\Laravel\SEO\Support\SEOData;

use function view;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (config('pwa-favicon.enabled')) {
            FilamentView::registerRenderHook(PanelsRenderHook::HEAD_START, fn (): View => view('components.favicon'));
        }
        FilamentView::registerRenderHook(PanelsRenderHook::HEAD_START, fn (): View => view('components.js-md5'));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! app()->isLocal()) {
            URL::forceHttps();
            Vite::useAggressivePrefetching();
        }

        // Strip Livewire's data-navigate-track="reload" from Vite-injected
        // assets — no panel uses SPA navigate, and the attribute otherwise
        // forces a hard CSS/JS reload on every full-page navigation.
        //
        // `data-cfasync="false"` opts the bundle out of Cloudflare's
        // Rocket Loader. Rocket Loader defers + reorders script execution,
        // which breaks `navigator.serviceWorker.register()` timing and
        // makes `pushManager.subscribe()` fail with the generic
        // "AbortError: Registration failed — push service error".
        Vite::useStyleTagAttributes(['data-navigate-track' => false]);
        Vite::useScriptTagAttributes([
            'data-navigate-track' => false,
            'data-cfasync' => 'false',
        ]);

        Model::automaticallyEagerLoadRelationships();

        // README HTML is rendered through jeffersongoncalves/laravel-markdown
        // (GFM + heading permalinks + server-side syntax highlighting) rather
        // than the package's plain CommonMark default. Wired at runtime so no
        // closure leaks into a cached config. Sanitised later before display.
        config(['github-readme.renderer' => static fn (string $markdown): string => Markdown::render($markdown, headingPermalinks: true)]);

        // SetLocale is a route-group middleware, so Livewire /update requests
        // (live search/sort/topic/pagination on /projects, /links, /articles)
        // would otherwise run without it and re-render under the default locale,
        // flipping en/es visitors to pt_BR mid-interaction. Persist it so it
        // re-runs on every component update.
        Livewire::addPersistentMiddleware(SetLocale::class);

        Paginator::defaultView('pagination.site');
        Paginator::defaultSimpleView('pagination.site-simple');

        $this->configureSeo();
        $this->configureRateLimiting();
    }

    private function configureSeo(): void
    {
        SEOManager::SEODataTransformer(function (SEOData $data): SEOData {
            if (empty($data->image)) {
                $data->image = Vite::asset('resources/images/github-og-'.LocaleSupport::short().'.png');
            }

            // Emit og:locale for the active language (OpenGraph wants the
            // language_TERRITORY form). Without this laravel-seo skips the tag.
            if (empty($data->locale)) {
                $data->locale = match (LocaleSupport::short()) {
                    'en' => 'en_US',
                    'es' => 'es_ES',
                    default => 'pt_BR',
                };
            }

            return $data;
        });
    }

    /**
     * Cap GitHub-API job throughput so parallel Horizon workers can't trip
     * GitHub's *secondary* rate limit (~900 pts/min, separate from the 5000/h
     * primary). 60 jobs/min × ~3 calls ≈ 180 calls/min — wide margin. The
     * `RateLimited('github-api')` middleware on SyncProjectMetricsJob releases
     * over-limit jobs back to the queue instead of failing them.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('github-api', fn () => Limit::perMinute(60));
        // Packagist also rate-limits; keep verification jobs well under it.
        RateLimiter::for('packagist-api', fn () => Limit::perMinute(30));
    }
}

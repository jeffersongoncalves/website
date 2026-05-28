<?php

namespace App\Http\Controllers\Site;

use App\Support\AppVersion;
use Illuminate\Http\Response;

class ServiceWorkerController
{
    /**
     * Serve `/sw.js` from a blade view so the cache version is bumped on
     * every release without rebuilding assets. The `Service-Worker-Allowed`
     * header is set explicitly so the browser accepts the registration at
     * the site root even if the SW is technically served from a different
     * path — defensive, since this controller is mounted at `/sw.js`
     * already, but no-cost. `Cache-Control: no-cache` makes browsers
     * re-validate the SW file on every navigation: Chrome / Firefox still
     * cap SW max-age at 24h, but no-cache makes the update path
     * deterministic when a tag is pushed.
     */
    public function __invoke(): Response
    {
        $content = view('sw.script', [
            'version' => AppVersion::current(),
        ])->render();

        return response($content, 200)
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Service-Worker-Allowed', '/')
            ->header('Cache-Control', 'no-cache, max-age=0, must-revalidate');
    }
}

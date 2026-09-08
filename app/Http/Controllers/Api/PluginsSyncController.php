<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Jobs\SyncPluginsJsonJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/plugins-sync — called by jeffersongoncalves/jeffersongoncalves's
 * notify-site-plugins-sync workflow whenever plugins.json changes on push.
 * Bearer-token protected (services.plugins_sync.token); queues the actual
 * scan instead of blocking the webhook on it.
 */
class PluginsSyncController
{
    public function __invoke(Request $request): JsonResponse
    {
        $expected = (string) config('services.plugins_sync.token');
        $given = (string) $request->bearerToken();

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        SyncPluginsJsonJob::dispatch();

        return response()->json(['status' => 'queued'], 202);
    }
}

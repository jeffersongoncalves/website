<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * /sitemap.xml — serves the file written to storage/app by `sitemap:generate`
 * (daily cron + on project change via GenerateSitemapJob). storage/app, unlike
 * public/, survives an atomic deploy's release swap, and a plain file read
 * keeps this route cheap (no DB query per request).
 */
class SitemapController
{
    public function __invoke(): Response
    {
        $xml = Storage::disk('local')->get('sitemap.xml');

        abort_if($xml === null, SymfonyResponse::HTTP_NOT_FOUND);

        return response($xml)->header('Content-Type', 'application/xml; charset=utf-8');
    }
}

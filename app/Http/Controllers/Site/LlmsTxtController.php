<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * /llms.txt — serves the file written to storage/app by `llms:generate`
 * (daily cron + on project change via GenerateLlmsTxtJob). storage/app,
 * unlike public/, survives an atomic deploy's release swap, and a plain file
 * read keeps this route cheap (no DB query per request).
 */
class LlmsTxtController
{
    public function __invoke(): Response
    {
        $body = Storage::disk('local')->get('llms.txt');

        abort_if($body === null, SymfonyResponse::HTTP_NOT_FOUND);

        return response($body)->header('Content-Type', 'text/plain; charset=utf-8');
    }
}

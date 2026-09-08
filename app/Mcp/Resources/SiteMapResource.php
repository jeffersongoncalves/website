<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Http\Controllers\Site\LlmsTxtController;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

/**
 * The site's /llms.txt body (llmstxt.org convention) as an MCP resource — the
 * same cached page map already served over HTTP, so a client gets it inline
 * instead of an extra fetch.
 */
#[Uri('site://llms.txt')]
#[MimeType('text/plain')]
#[Description('Plain-text map of every section of the portfolio, plus authored open-source projects and articles.')]
class SiteMapResource extends Resource
{
    public function handle(Request $request): Response
    {
        return Response::text((string) app(LlmsTxtController::class)->__invoke()->getContent());
    }
}

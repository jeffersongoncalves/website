<?php

declare(strict_types=1);

use App\Mcp\Servers\PortfolioServer;
use JeffersonGoncalves\SecurityHeaders\Middleware\SecurityHeaders;
use Laravel\Mcp\Facades\Mcp;

// Public, read-only MCP server over the portfolio's published content — same
// data /projects, /articles and /llms.txt already expose, just queryable by
// an MCP client instead of scraped. No auth: nothing served here is behind
// login. Throttled like the other public relay endpoints (og.show,
// favicon-proxy) to keep it from becoming a free scraping API.
Mcp::web('/mcp', PortfolioServer::class)
    ->middleware([SecurityHeaders::class, 'throttle:60,1']);

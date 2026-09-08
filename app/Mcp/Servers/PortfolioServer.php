<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Resources\SiteMapResource;
use App\Mcp\Tools\GetPageTool;
use App\Mcp\Tools\SearchProjectsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Jefferson Gonçalves Portfolio')]
#[Version('1.0.0')]
#[Instructions('Query this developer portfolio: open-source projects (Filament plugins, Laravel packages, starter kits, tools), technical articles and curated external links. Use search_projects to find pages, get_page to read one in full, and the site://llms.txt resource for a full map of the site.')]
class PortfolioServer extends Server
{
    protected array $tools = [
        SearchProjectsTool::class,
        GetPageTool::class,
    ];

    protected array $resources = [
        SiteMapResource::class,
    ];

    protected array $prompts = [
        //
    ];
}

<?php

declare(strict_types=1);

namespace App\Livewire\Site;

use Illuminate\Contracts\View\View;
use JeffersonGoncalves\HtmlSanitizer\HtmlSanitizer;
use JeffersonGoncalves\LocaleCookie\LocaleCookie;
use JeffersonGoncalves\Markdown\Markdown;
use Livewire\Component;

/**
 * Full-page Livewire component for /mcp-guide. Static content (no wire
 * actions), so the route stays in the page cache. Body is authored as
 * per-locale markdown and rendered through the same pipeline as READMEs/
 * articles (App\Support\GithubReadme's renderer, sanitized before display)
 * so the fenced code blocks get the same syntax highlighting + copy-button
 * treatment as everywhere else on the site — no bespoke markup needed.
 */
class McpGuidePage extends Component
{
    public function render(): View
    {
        $locale = LocaleCookie::short();
        $endpoint = url('/mcp');

        $bodyHtml = HtmlSanitizer::clean(Markdown::render($this->body($locale, $endpoint), headingPermalinks: true));

        return view('livewire.site.mcp-guide-page', ['bodyHtml' => $bodyHtml])
            ->layout('components.site.layouts.app', [
                'title' => __('site.mcp_guide.title'),
                'description' => __('site.seo.mcp_guide'),
            ]);
    }

    private function body(string $locale, string $endpoint): string
    {
        return match ($locale) {
            'es' => <<<MD
                Este sitio publica un servidor **MCP** (Model Context Protocol) público y de solo lectura sobre el mismo catálogo que ves en /projects, /articles y /links — así un asistente de IA (Claude, etc.) puede buscar y leer estas páginas directamente, sin scraping de HTML.

                ## Endpoint

                ```
                {$endpoint}
                ```

                Transporte HTTP (Streamable HTTP), sin autenticación — el contenido ya es público. Limitado a 60 solicitudes/min por IP.

                ## Agregarlo en Claude Code

                ```bash
                claude mcp add --transport http portfolio {$endpoint}
                ```

                ## Agregarlo en un cliente MCP genérico

                ```json
                {
                  "mcpServers": {
                    "portfolio": {
                      "url": "{$endpoint}"
                    }
                  }
                }
                ```

                ## Probarlo localmente con el MCP Inspector

                ```bash
                php artisan mcp:inspector mcp/portfolio
                ```

                ## Qué expone

                - **search_projects** — busca proyectos, artículos y links curados por texto, categoría o sección.
                - **get_page** — trae una página por su slug: metadata, enlaces (GitHub/Packagist/npm/docs/demo) y un extracto del README.
                - **Resource `site://llms.txt`** — el mismo mapa de página en texto plano que sirve /llms.txt.
                MD,
            'en' => <<<MD
                This site publishes a public, read-only **MCP** (Model Context Protocol) server over the same catalogue you see on /projects, /articles and /links — so an AI assistant (Claude, etc.) can search and read these pages directly, no HTML scraping needed.

                ## Endpoint

                ```
                {$endpoint}
                ```

                HTTP transport (Streamable HTTP), no auth — the content is already public. Throttled to 60 requests/min per IP.

                ## Add it in Claude Code

                ```bash
                claude mcp add --transport http portfolio {$endpoint}
                ```

                ## Add it in a generic MCP client

                ```json
                {
                  "mcpServers": {
                    "portfolio": {
                      "url": "{$endpoint}"
                    }
                  }
                }
                ```

                ## Test it locally with the MCP Inspector

                ```bash
                php artisan mcp:inspector mcp/portfolio
                ```

                ## What it exposes

                - **search_projects** — search projects, articles and curated links by text, category or section.
                - **get_page** — fetch one page by its slug: metadata, links (GitHub/Packagist/npm/docs/demo) and a README excerpt.
                - **Resource `site://llms.txt`** — the same plain-text page map served at /llms.txt.
                MD,
            default => <<<MD
                Este site publica um servidor **MCP** (Model Context Protocol) público e somente leitura sobre o mesmo catálogo que você vê em /projects, /articles e /links — assim um assistente de IA (Claude, etc.) consegue buscar e ler essas páginas direto, sem precisar raspar HTML.

                ## Endpoint

                ```
                {$endpoint}
                ```

                Transporte HTTP (Streamable HTTP), sem autenticação — o conteúdo já é público. Limitado a 60 requisições/min por IP.

                ## Adicionar no Claude Code

                ```bash
                claude mcp add --transport http portfolio {$endpoint}
                ```

                ## Adicionar em um cliente MCP genérico

                ```json
                {
                  "mcpServers": {
                    "portfolio": {
                      "url": "{$endpoint}"
                    }
                  }
                }
                ```

                ## Testar localmente com o MCP Inspector

                ```bash
                php artisan mcp:inspector mcp/portfolio
                ```

                ## O que ele expõe

                - **search_projects** — busca projetos, artigos e links curados por texto, categoria ou seção.
                - **get_page** — busca uma página pelo slug: metadados, links (GitHub/Packagist/npm/docs/demo) e um trecho do README.
                - **Resource `site://llms.txt`** — o mesmo mapa de páginas em texto puro servido em /llms.txt.
                MD,
        };
    }
}

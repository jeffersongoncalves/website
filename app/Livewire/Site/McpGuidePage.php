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

        return view('livewire.site.mcp-guide-page', ['bodyHtml' => $bodyHtml, 'endpoint' => $endpoint])
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

                Transporte HTTP (Streamable HTTP), sin autenticación — el contenido ya es público. Limitado a 60 solicitudes/min por IP. Más abajo hay configuración lista para copiar según tu asistente de IA.

                ## Probarlo localmente con el MCP Inspector

                ```bash
                php artisan mcp:inspector mcp/portfolio
                ```

                ## Qué expone

                - **search_projects** — busca proyectos, artículos y links curados por texto (`query`), sección (`section`: `projects`/`articles`/`links`), categoría exacta (`category`, p. ej. `filament_plugin`, `laravel_package`, `starter_kit`) y un límite de resultados (`limit`, 1-50). Devuelve una lista en Markdown con nombre, URL canónica, categoría y un resumen de una línea.
                - **get_page** — trae una página publicada por su `slug` (el mismo que devuelve search_projects): categoría, enlaces salientes disponibles (GitHub, Packagist, npm, Docker Hub, docs, demo), estrellas, descargas, licencia, tipo de paquete, lenguaje, versiones de Filament soportadas, stack y topics, más un extracto del README en texto plano (hasta 3000 caracteres, HTML ya saneado y sin marcado).
                - **get_site_stats** — números agregados de todo el portafolio: repos, total de páginas del catálogo, descargas por registro (Packagist/npm/JetBrains/Docker), estrellas, seguidores y sponsors de GitHub, conteo de paquetes por categoría y los lenguajes/topics más frecuentes. Sin argumentos.
                - **Resource `site://llms.txt`** — el mismo mapa de página en texto plano que sirve /llms.txt: cada página pública del sitio con su URL, pensado para que un asistente descubra el catálogo completo de una sola vez.

                ## Notas técnicas

                Las tres tools son de solo lectura y consultan las mismas filas y el mismo scope `published()` que usan /projects, /articles y /links (get_site_stats lee el mismo agregado cacheado que las tarjetas de estadísticas del sitio) — no hay contenido oculto ni mutaciones posibles. El servidor corre sobre Laravel MCP con transporte Streamable HTTP; no requiere sesión ni handshake adicional más allá del propio protocolo MCP.
                MD,
            'en' => <<<MD
                This site publishes a public, read-only **MCP** (Model Context Protocol) server over the same catalogue you see on /projects, /articles and /links — so an AI assistant (Claude, etc.) can search and read these pages directly, no HTML scraping needed.

                ## Endpoint

                ```
                {$endpoint}
                ```

                HTTP transport (Streamable HTTP), no auth — the content is already public. Throttled to 60 requests/min per IP. Ready-to-copy config for your AI assistant is further down this page.

                ## Test it locally with the MCP Inspector

                ```bash
                php artisan mcp:inspector mcp/portfolio
                ```

                ## What it exposes

                - **search_projects** — search projects, articles and curated links by free text (`query`), section (`section`: `projects`/`articles`/`links`), an exact category (`category`, e.g. `filament_plugin`, `laravel_package`, `starter_kit`) and a result limit (`limit`, 1-50). Returns a Markdown list with each page's name, canonical URL, category and a one-line summary.
                - **get_page** — fetch one published page by its `slug` (the same one search_projects returns): category, whichever outbound links exist (GitHub, Packagist, npm, Docker Hub, docs, demo), stars, downloads, license, package type, language, supported Filament versions, stack and topics, plus a plain-text README excerpt (up to 3000 characters, already sanitized and stripped of markup).
                - **get_site_stats** — aggregate numbers across the whole portfolio: repo and catalogue-page counts, downloads by registry (Packagist/npm/JetBrains/Docker), stars, GitHub followers and sponsors, package counts by category, and the busiest languages/topics. No arguments.
                - **Resource `site://llms.txt`** — the same plain-text page map served at /llms.txt: every public page on the site with its URL, meant to let an assistant discover the whole catalogue in one read.

                ## Technical notes

                All three tools are read-only: search_projects and get_page query the exact same rows and `published()` scope that /projects, /articles and /links use, and get_site_stats reads the same cached aggregate the site's own stat cards use — nothing hidden, nothing mutable. The server runs on Laravel MCP over Streamable HTTP transport; no session or handshake beyond the MCP protocol itself.
                MD,
            default => <<<MD
                Este site publica um servidor **MCP** (Model Context Protocol) público e somente leitura sobre o mesmo catálogo que você vê em /projects, /articles e /links — assim um assistente de IA (Claude, etc.) consegue buscar e ler essas páginas direto, sem precisar raspar HTML.

                ## Endpoint

                ```
                {$endpoint}
                ```

                Transporte HTTP (Streamable HTTP), sem autenticação — o conteúdo já é público. Limitado a 60 requisições/min por IP. Mais abaixo tem configuração pronta pra copiar de acordo com seu assistente de IA.

                ## Testar localmente com o MCP Inspector

                ```bash
                php artisan mcp:inspector mcp/portfolio
                ```

                ## O que ele expõe

                - **search_projects** — busca projetos, artigos e links curados por texto livre (`query`), seção (`section`: `projects`/`articles`/`links`), categoria exata (`category`, ex: `filament_plugin`, `laravel_package`, `starter_kit`) e um limite de resultados (`limit`, 1-50). Devolve uma lista em Markdown com nome, URL canônica, categoria e um resumo de uma linha por página.
                - **get_page** — busca uma página publicada pelo `slug` (o mesmo que search_projects devolve): categoria, links de saída disponíveis (GitHub, Packagist, npm, Docker Hub, docs, demo), estrelas, downloads, licença, tipo de pacote, linguagem, versões de Filament suportadas, stack e topics, além de um trecho do README em texto puro (até 3000 caracteres, HTML já sanitizado e sem marcação).
                - **get_site_stats** — números agregados de todo o portfólio: total de repos e páginas do catálogo, downloads por registro (Packagist/npm/JetBrains/Docker), estrelas, seguidores e sponsors do GitHub, contagem de pacotes por categoria e as linguagens/topics mais frequentes. Sem argumentos.
                - **Resource `site://llms.txt`** — o mesmo mapa de páginas em texto puro servido em /llms.txt: cada página pública do site com sua URL, pensado pra um assistente descobrir o catálogo inteiro numa leitura só.

                ## Detalhes técnicos

                As três tools são somente leitura: search_projects e get_page consultam exatamente as mesmas linhas e o mesmo escopo `published()` que /projects, /articles e /links usam, e get_site_stats lê o mesmo agregado cacheado dos cards de estatística do site — nada escondido, nada mutável. O servidor roda sobre Laravel MCP com transporte Streamable HTTP; sem sessão nem handshake além do próprio protocolo MCP.
                MD,
        };
    }
}

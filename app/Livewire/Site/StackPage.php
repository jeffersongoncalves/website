<?php

declare(strict_types=1);

namespace App\Livewire\Site;

use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Full-page Livewire component for /stack. Static content (no wire actions), so
 * the route stays in the page cache. The group data and the Packagist→catalogue
 * slug mapping are unchanged from the old StackController.
 */
class StackPage extends Component
{
    public function render(): View
    {
        $groups = $this->groups();

        // Prefer the package's own page on this site (the catalogue) over an
        // external Packagist link. One query maps packagist_url → slug; items
        // not in the catalogue fall back to Packagist.
        $packagistUrls = [];
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                if (! empty($item['packagist'])) {
                    $packagistUrls[] = 'https://packagist.org/packages/'.$item['packagist'];
                }
            }
        }

        $slugByPackagist = $packagistUrls === []
            ? collect()
            : Project::query()->published()->whereIn('packagist_url', $packagistUrls)->pluck('slug', 'packagist_url');

        foreach ($groups as $gi => $group) {
            foreach ($group['items'] as $ii => $item) {
                if (empty($item['packagist'])) {
                    continue;
                }

                $packagistUrl = 'https://packagist.org/packages/'.$item['packagist'];
                $slug = $slugByPackagist[$packagistUrl] ?? null;

                $groups[$gi]['items'][$ii]['url'] = $slug
                    ? route('projects.show', ['slug' => $slug])
                    : $packagistUrl;
                $groups[$gi]['items'][$ii]['internal'] = $slug !== null;
            }
        }

        return view('livewire.site.stack-page', ['groups' => $groups])
            ->layout('components.site.layouts.app', [
                'title' => __('site.stack.title'),
                'description' => __('site.seo.stack'),
            ]);
    }

    /**
     * The technologies powering this site, grouped. Versions are masked to the
     * major line only (e.g. "13.x"). Composer packages are listed under their
     * own group with a Packagist link; the ones authored by the site owner are
     * flagged.
     *
     * @return list<array{pt: string, en: string, es: string, items: list<array{name: string, version?: ?string, pt: string, en: string, es: string, packagist?: string, author?: bool}>}>
     */
    private function groups(): array
    {
        return [
            [
                'pt' => 'Plataforma', 'en' => 'Platform', 'es' => 'Plataforma',
                'items' => [
                    ['name' => 'PHP', 'version' => '8.x',
                        'pt' => 'Linguagem server-side que roda toda a aplicação.',
                        'en' => 'Server-side language running the whole application.',
                        'es' => 'Lenguaje del servidor que ejecuta toda la aplicación.'],
                    ['name' => 'Laravel', 'version' => '13.x',
                        'pt' => 'Framework PHP que sustenta rotas, ORM, filas e jobs.',
                        'en' => 'PHP framework behind routing, ORM, queues and jobs.',
                        'es' => 'Framework PHP detrás de rutas, ORM, colas y jobs.'],
                    ['name' => 'Livewire', 'version' => '4.x',
                        'pt' => 'Componentes reativos full-stack sem escrever JavaScript.',
                        'en' => 'Full-stack reactive components with no hand-written JS.',
                        'es' => 'Componentes reactivos full-stack sin escribir JS.'],
                    ['name' => 'Filament', 'version' => '5.x',
                        'pt' => 'Painel administrativo e construção de UI.',
                        'en' => 'Admin panel and UI building toolkit.',
                        'es' => 'Panel de administración y construcción de UI.'],
                ],
            ],
            [
                'pt' => 'Frontend', 'en' => 'Frontend', 'es' => 'Frontend',
                'items' => [
                    ['name' => 'Tailwind CSS', 'version' => '4.x',
                        'pt' => 'CSS utilitário que dá forma a toda a interface.',
                        'en' => 'Utility-first CSS shaping the whole interface.',
                        'es' => 'CSS de utilidades que da forma a toda la interfaz.'],
                    ['name' => 'Alpine.js', 'version' => '3.x',
                        'pt' => 'Interatividade leve direto no navegador.',
                        'en' => 'Lightweight interactivity straight in the browser.',
                        'es' => 'Interactividad ligera directa en el navegador.'],
                    ['name' => 'Vite', 'version' => '8.x',
                        'pt' => 'Bundler e dev server dos assets.',
                        'en' => 'Asset bundler and dev server.',
                        'es' => 'Bundler y dev server de los assets.'],
                ],
            ],
            [
                'pt' => 'Pacotes', 'en' => 'Packages', 'es' => 'Paquetes',
                'items' => [
                    // Authored by the site owner.
                    ['name' => 'jeffersongoncalves/filament-gtag', 'author' => true, 'packagist' => 'jeffersongoncalves/filament-gtag',
                        'pt' => 'Google Analytics (gtag) integrado ao Filament.',
                        'en' => 'Google Analytics (gtag) integrated into Filament.',
                        'es' => 'Google Analytics (gtag) integrado en Filament.'],
                    ['name' => 'jeffersongoncalves/filament-gtm', 'author' => true, 'packagist' => 'jeffersongoncalves/filament-gtm',
                        'pt' => 'Google Tag Manager integrado ao Filament.',
                        'en' => 'Google Tag Manager integrated into Filament.',
                        'es' => 'Google Tag Manager integrado en Filament.'],
                    ['name' => 'jeffersongoncalves/filament-logo', 'author' => true, 'packagist' => 'jeffersongoncalves/filament-logo',
                        'pt' => 'Logo e favicon do painel.',
                        'en' => 'Panel logo and favicon.',
                        'es' => 'Logo y favicon del panel.'],
                    ['name' => 'jeffersongoncalves/filament-one-time-operations', 'author' => true, 'packagist' => 'jeffersongoncalves/filament-one-time-operations',
                        'pt' => 'Operações one-time direto no painel.',
                        'en' => 'One-time operations from the panel.',
                        'es' => 'Operaciones one-time desde el panel.'],
                    ['name' => 'jeffersongoncalves/filament-additional-information', 'author' => true, 'packagist' => 'jeffersongoncalves/filament-additional-information',
                        'pt' => 'Seção de infolist reutilizável para timestamps de criado/atualizado.',
                        'en' => 'Reusable infolist section for created/updated timestamps.',
                        'es' => 'Sección de infolist reutilizable para timestamps de creado/actualizado.'],
                    ['name' => 'jeffersongoncalves/filament-sensible-defaults', 'author' => true, 'packagist' => 'jeffersongoncalves/filament-sensible-defaults',
                        'pt' => 'Padrões globais de UI aplicados a todo o Filament.',
                        'en' => 'Global UI defaults applied across Filament.',
                        'es' => 'Valores por defecto globales de UI aplicados a todo Filament.'],
                    ['name' => 'jeffersongoncalves/filament-pwa', 'author' => true, 'packagist' => 'jeffersongoncalves/filament-pwa',
                        'pt' => 'Injeta o <head> PWA completo nos painéis Filament.',
                        'en' => 'Injects the full PWA <head> into Filament panels.',
                        'es' => 'Inyecta el <head> PWA completo en los paneles Filament.'],
                    ['name' => 'jeffersongoncalves/laravel-pwa-favicon', 'author' => true, 'packagist' => 'jeffersongoncalves/laravel-pwa-favicon',
                        'pt' => 'Manifesto PWA, favicons e o <head> instalável.',
                        'en' => 'PWA manifest, favicons and the installable <head>.',
                        'es' => 'Manifiesto PWA, favicons y el <head> instalable.'],
                    ['name' => 'jeffersongoncalves/laravel-markdown', 'author' => true, 'packagist' => 'jeffersongoncalves/laravel-markdown',
                        'pt' => 'Renderizador Markdown (GFM + syntax highlight no servidor).',
                        'en' => 'Markdown renderer (GFM + server-side syntax highlighting).',
                        'es' => 'Renderizador Markdown (GFM + resaltado de sintaxis en servidor).'],
                    ['name' => 'jeffersongoncalves/laravel-github-readme', 'author' => true, 'packagist' => 'jeffersongoncalves/laravel-github-readme',
                        'pt' => 'Busca, cacheia e renderiza READMEs do GitHub.',
                        'en' => 'Fetches, caches and renders GitHub READMEs.',
                        'es' => 'Obtiene, cachea y renderiza READMEs de GitHub.'],
                    ['name' => 'jeffersongoncalves/laravel-github-client', 'author' => true, 'packagist' => 'jeffersongoncalves/laravel-github-client',
                        'pt' => 'Cliente da API do GitHub com tratamento de rate limit.',
                        'en' => 'GitHub API client with rate-limit handling.',
                        'es' => 'Cliente de la API de GitHub con manejo de rate limit.'],
                    ['name' => 'jeffersongoncalves/laravel-github-contributions', 'author' => true, 'packagist' => 'jeffersongoncalves/laravel-github-contributions',
                        'pt' => 'Dados do gráfico de contribuições do GitHub.',
                        'en' => 'GitHub contributions graph data.',
                        'es' => 'Datos del gráfico de contribuciones de GitHub.'],
                    ['name' => 'jeffersongoncalves/laravel-html-sanitizer', 'author' => true, 'packagist' => 'jeffersongoncalves/laravel-html-sanitizer',
                        'pt' => 'Sanitiza HTML não confiável (proteção XSS).',
                        'en' => 'Sanitizes untrusted HTML (XSS protection).',
                        'es' => 'Sanitiza HTML no confiable (protección XSS).'],
                    ['name' => 'jeffersongoncalves/laravel-security-headers', 'author' => true, 'packagist' => 'jeffersongoncalves/laravel-security-headers',
                        'pt' => 'Middleware de headers HTTP de segurança.',
                        'en' => 'Security HTTP headers middleware.',
                        'es' => 'Middleware de cabeceras HTTP de seguridad.'],
                    ['name' => 'jeffersongoncalves/laravel-ssrf-guard', 'author' => true, 'packagist' => 'jeffersongoncalves/laravel-ssrf-guard',
                        'pt' => 'Proteção SSRF (IP público / DNS rebind) em fetches externos.',
                        'en' => 'SSRF guard (public-IP / DNS-rebind) for outbound fetches.',
                        'es' => 'Protección SSRF (IP público / DNS rebind) en fetches externos.'],
                    ['name' => 'jeffersongoncalves/laravel-page-cache', 'author' => true, 'packagist' => 'jeffersongoncalves/laravel-page-cache',
                        'pt' => 'Cache de página inteira para as páginas públicas.',
                        'en' => 'Full-page cache for the public pages.',
                        'es' => 'Caché de página completa para las páginas públicas.'],
                    ['name' => 'jeffersongoncalves/laravel-locale-cookie', 'author' => true, 'packagist' => 'jeffersongoncalves/laravel-locale-cookie',
                        'pt' => 'Resolve o locale a partir de um cookie.',
                        'en' => 'Resolves the locale from a cookie.',
                        'es' => 'Resuelve el locale desde una cookie.'],

                    // Community packages.
                    ['name' => 'laravel/horizon', 'packagist' => 'laravel/horizon',
                        'pt' => 'Dashboard e workers das filas Redis.',
                        'en' => 'Dashboard and workers for the Redis queues.',
                        'es' => 'Dashboard y workers de las colas Redis.'],
                    ['name' => 'laravel/tinker', 'packagist' => 'laravel/tinker',
                        'pt' => 'REPL para o Laravel.',
                        'en' => 'REPL for Laravel.',
                        'es' => 'REPL para Laravel.'],
                    ['name' => 'ralphjsmit/laravel-seo', 'packagist' => 'ralphjsmit/laravel-seo',
                        'pt' => 'Meta tags e dados estruturados de SEO.',
                        'en' => 'SEO meta tags and structured data.',
                        'es' => 'Meta tags y datos estructurados de SEO.'],
                    ['name' => 'spatie/laravel-sitemap', 'packagist' => 'spatie/laravel-sitemap',
                        'pt' => 'Geração do sitemap.xml.',
                        'en' => 'sitemap.xml generation.',
                        'es' => 'Generación del sitemap.xml.'],
                    ['name' => 'spatie/laravel-sluggable', 'packagist' => 'spatie/laravel-sluggable',
                        'pt' => 'Slugs automáticos para os projetos.',
                        'en' => 'Automatic slugs for the projects.',
                        'es' => 'Slugs automáticos para los proyectos.'],
                    ['name' => 'spatie/laravel-translatable', 'packagist' => 'spatie/laravel-translatable',
                        'pt' => 'Campos traduzíveis (pt/en/es).',
                        'en' => 'Translatable fields (pt/en/es).',
                        'es' => 'Campos traducibles (pt/en/es).'],
                    ['name' => 'achyutn/filament-log-viewer', 'packagist' => 'achyutn/filament-log-viewer',
                        'pt' => 'Visualizador de logs no painel.',
                        'en' => 'Log viewer in the panel.',
                        'es' => 'Visor de logs en el panel.'],
                    ['name' => 'dutchcodingcompany/filament-developer-logins', 'packagist' => 'dutchcodingcompany/filament-developer-logins',
                        'pt' => 'Login rápido de desenvolvimento.',
                        'en' => 'Quick developer logins.',
                        'es' => 'Login rápido de desarrollo.'],
                    ['name' => 'joaopaulolndev/filament-edit-profile', 'packagist' => 'joaopaulolndev/filament-edit-profile',
                        'pt' => 'Edição de perfil do usuário.',
                        'en' => 'User profile editing.',
                        'es' => 'Edición del perfil de usuario.'],
                ],
            ],
            [
                'pt' => 'Dados', 'en' => 'Data', 'es' => 'Datos',
                'items' => [
                    ['name' => 'PostgreSQL', 'version' => '18.x',
                        'pt' => 'Banco de dados relacional principal.',
                        'en' => 'Primary relational database.',
                        'es' => 'Base de datos relacional principal.'],
                    ['name' => 'Redis', 'version' => null,
                        'pt' => 'Cache da aplicação e backend das filas.',
                        'en' => 'Application cache and queue backend.',
                        'es' => 'Caché de la aplicación y backend de colas.'],
                ],
            ],
            [
                'pt' => 'Infra & Deploy', 'en' => 'Infra & Deploy', 'es' => 'Infra & Deploy',
                'items' => [
                    ['name' => 'Laravel Forge', 'version' => null,
                        'pt' => 'Provisionamento do servidor e deploy da aplicação.',
                        'en' => 'Server provisioning and application deploys.',
                        'es' => 'Aprovisionamiento del servidor y deploy de la aplicación.'],
                    ['name' => 'Cloudflare', 'version' => null,
                        'pt' => 'CDN, DNS e proxy na frente do site.',
                        'en' => 'CDN, DNS and proxy in front of the site.',
                        'es' => 'CDN, DNS y proxy delante del sitio.'],
                ],
            ],
            [
                'pt' => 'Qualidade', 'en' => 'Quality', 'es' => 'Calidad',
                'items' => [
                    ['name' => 'Pest', 'version' => '4.x',
                        'pt' => 'Suíte de testes automatizados.',
                        'en' => 'Automated test suite.',
                        'es' => 'Suite de pruebas automatizadas.'],
                    ['name' => 'PHPStan', 'version' => null,
                        'pt' => 'Análise estática para pegar erros antes do deploy.',
                        'en' => 'Static analysis catching bugs before deploy.',
                        'es' => 'Análisis estático para detectar errores antes del deploy.'],
                    ['name' => 'Laravel Pint', 'version' => null,
                        'pt' => 'Padronização automática do estilo de código.',
                        'en' => 'Automatic code-style formatting.',
                        'es' => 'Formato automático del estilo de código.'],
                ],
            ],
        ];
    }
}

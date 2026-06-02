<?php

namespace App\Http\Controllers\Site;

use App\Models\Project;
use Illuminate\Contracts\View\View;

class StackController
{
    public function __invoke(): View
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

        return view('site.stack', ['groups' => $groups]);
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
                'pt' => 'Dados & Realtime', 'en' => 'Data & Realtime', 'es' => 'Datos & Realtime',
                'items' => [
                    ['name' => 'PostgreSQL', 'version' => '18.x',
                        'pt' => 'Banco de dados relacional principal.',
                        'en' => 'Primary relational database.',
                        'es' => 'Base de datos relacional principal.'],
                    ['name' => 'Redis', 'version' => null,
                        'pt' => 'Cache da aplicação e backend das filas.',
                        'en' => 'Application cache and queue backend.',
                        'es' => 'Caché de la aplicación y backend de colas.'],
                    ['name' => 'MinIO', 'version' => null,
                        'pt' => 'Object storage compatível com S3.',
                        'en' => 'S3-compatible object storage.',
                        'es' => 'Object storage compatible con S3.'],
                    ['name' => 'Soketi', 'version' => null,
                        'pt' => 'Servidor WebSocket (realtime, compatível com Pusher).',
                        'en' => 'WebSocket server (realtime, Pusher-compatible).',
                        'es' => 'Servidor WebSocket (realtime, compatible con Pusher).'],
                ],
            ],
            [
                'pt' => 'Infra & Deploy', 'en' => 'Infra & Deploy', 'es' => 'Infra & Deploy',
                'items' => [
                    ['name' => 'Kronn.io', 'version' => null,
                        'pt' => 'Plataforma que orquestra os containers do servidor.',
                        'en' => 'Platform orchestrating the server containers.',
                        'es' => 'Plataforma que orquesta los contenedores del servidor.'],
                    ['name' => 'Docker', 'version' => null,
                        'pt' => 'Apps em containers: nginx + PHP-FPM + supervisord.',
                        'en' => 'Apps in containers: nginx + PHP-FPM + supervisord.',
                        'es' => 'Apps en contenedores: nginx + PHP-FPM + supervisord.'],
                    ['name' => 'GHCR', 'version' => null,
                        'pt' => 'Registry das imagens — deploy versionado por release.',
                        'en' => 'Image registry — release-versioned deploys.',
                        'es' => 'Registry de imágenes — deploys versionados por release.'],
                    ['name' => 'Cloudflare', 'version' => null,
                        'pt' => 'CDN, DNS e proxy na frente do site.',
                        'en' => 'CDN, DNS and proxy in front of the site.',
                        'es' => 'CDN, DNS y proxy delante del sitio.'],
                    ['name' => 'Traefik', 'version' => null,
                        'pt' => 'Reverse proxy / ingress com TLS automático (Let’s Encrypt).',
                        'en' => 'Reverse proxy / ingress with automatic TLS (Let’s Encrypt).',
                        'es' => 'Reverse proxy / ingress con TLS automático (Let’s Encrypt).'],
                    ['name' => 'GitHub Actions', 'version' => null,
                        'pt' => 'CI/CD: testes, análise e build da imagem.',
                        'en' => 'CI/CD: tests, analysis and image build.',
                        'es' => 'CI/CD: pruebas, análisis y build de la imagen.'],
                ],
            ],
            [
                'pt' => 'Observabilidade', 'en' => 'Observability', 'es' => 'Observabilidad',
                'items' => [
                    ['name' => 'Prometheus', 'version' => null,
                        'pt' => 'Exporters de métricas (Postgres, Redis, Traefik).',
                        'en' => 'Metrics exporters (Postgres, Redis, Traefik).',
                        'es' => 'Exporters de métricas (Postgres, Redis, Traefik).'],
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

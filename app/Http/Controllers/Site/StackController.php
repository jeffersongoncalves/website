<?php

namespace App\Http\Controllers\Site;

use Illuminate\Contracts\View\View;

class StackController
{
    public function __invoke(): View
    {
        return view('site.stack', ['groups' => $this->groups()]);
    }

    /**
     * The technologies powering this site, grouped. Versions are intentionally
     * masked to the major line only (e.g. "13.x") — never the exact pinned
     * version. Tools where a version adds no signal omit it.
     *
     * @return list<array{pt: string, en: string, es: string, items: list<array{name: string, version: ?string, pt: string, en: string, es: string}>}>
     */
    private function groups(): array
    {
        return [
            [
                'pt' => 'Backend', 'en' => 'Backend', 'es' => 'Backend',
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
                    ['name' => 'Laravel Horizon', 'version' => '5.x',
                        'pt' => 'Dashboard e workers das filas Redis.',
                        'en' => 'Dashboard and workers for the Redis queues.',
                        'es' => 'Dashboard y workers de las colas Redis.'],
                    ['name' => 'Prism', 'version' => null,
                        'pt' => 'Camada de integração com modelos de linguagem (LLM).',
                        'en' => 'Integration layer for large language models (LLM).',
                        'es' => 'Capa de integración con modelos de lenguaje (LLM).'],
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
                'pt' => 'Infra & Deploy', 'en' => 'Infra & Deploy', 'es' => 'Infra & Deploy',
                'items' => [
                    ['name' => 'PostgreSQL', 'version' => null,
                        'pt' => 'Banco de dados relacional em produção.',
                        'en' => 'Relational database in production.',
                        'es' => 'Base de datos relacional en producción.'],
                    ['name' => 'Redis', 'version' => null,
                        'pt' => 'Cache da aplicação e backend das filas.',
                        'en' => 'Application cache and queue backend.',
                        'es' => 'Caché de la aplicación y backend de colas.'],
                    ['name' => 'Docker', 'version' => null,
                        'pt' => 'Imagem de produção: nginx + PHP-FPM + supervisord.',
                        'en' => 'Production image: nginx + PHP-FPM + supervisord.',
                        'es' => 'Imagen de producción: nginx + PHP-FPM + supervisord.'],
                    ['name' => 'GitHub Actions', 'version' => null,
                        'pt' => 'CI/CD: testes, análise e build da imagem.',
                        'en' => 'CI/CD: tests, analysis and image build.',
                        'es' => 'CI/CD: pruebas, análisis y build de la imagen.'],
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

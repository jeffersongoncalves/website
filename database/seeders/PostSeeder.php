<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->posts() as $row) {
            Post::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'title' => [
                        'pt' => $row['title_pt'],
                        'en' => $row['title_en'] ?? $row['title_pt'],
                    ],
                    'excerpt' => [
                        'pt' => $row['excerpt_pt'],
                        'en' => $row['excerpt_en'] ?? $row['excerpt_pt'],
                    ],
                    'body' => [
                        'pt' => $row['body_pt'],
                        'en' => $row['body_en'] ?? $row['body_pt'],
                    ],
                    'tags'         => $row['tags'],
                    'reading_time' => $row['reading_time'],
                    'views'        => random_int(120, 5_000),
                    'status'       => PostStatus::Published->value,
                    'published_at' => $row['published_at'],
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function posts(): array
    {
        return [
            [
                'slug'         => 'multi-tenancy-real-filament-v3',
                'title_pt'     => 'Multi-tenancy real em Filament v3 sem sofrimento',
                'title_en'     => 'Real multi-tenancy in Filament v3 without pain',
                'excerpt_pt'   => 'Como estruturar tenants por subdomínio + database isolada usando spatie/laravel-multitenancy, com migrations e seeders limpos.',
                'excerpt_en'   => 'How to structure tenants by subdomain + isolated database using spatie/laravel-multitenancy, with clean migrations and seeders.',
                'body_pt'      => "Multi-tenancy é um daqueles assuntos que parecem simples até você precisar implementar de verdade.\n\nEste post é o setup que uso hoje em produção com spatie/laravel-multitenancy e Filament v3.",
                'body_en'      => "Multi-tenancy is one of those topics that look simple until you actually have to implement it.\n\nThis post is the setup I use today in production with spatie/laravel-multitenancy and Filament v3.",
                'tags'         => ['Filament', 'Multi-tenancy', 'Laravel'],
                'reading_time' => 8,
                'published_at' => Carbon::parse('2025-12-12'),
            ],
            [
                'slug'         => 'pacotes-composer-do-zero-ao-packagist',
                'title_pt'     => 'Pacotes Composer: do zero à publicação no Packagist',
                'title_en'     => 'Composer packages: from zero to publishing on Packagist',
                'excerpt_pt'   => 'Estrutura de diretórios, autoload PSR-4, testes com Pest, e o pipeline GitHub Actions que uso em todos os meus pacotes.',
                'excerpt_en'   => 'Directory structure, PSR-4 autoload, Pest tests, and the GitHub Actions pipeline I use in every package.',
                'body_pt'      => "Publicar pacote Composer no Packagist parece overhead até virar rotina.\n\nNeste guia mostro a estrutura mínima viável.",
                'body_en'      => "Publishing a Composer package on Packagist feels like overhead until it becomes routine.\n\nIn this guide I show the minimum viable structure.",
                'tags'         => ['Open Source', 'PHP', 'Laravel'],
                'reading_time' => 12,
                'published_at' => Carbon::parse('2025-12-03'),
            ],
            [
                'slug'         => 'filament-forms-sair-do-crud-generico',
                'title_pt'     => 'Filament Forms: o caso para sair do CRUD genérico',
                'title_en'     => 'Filament Forms: the case for moving beyond generic CRUD',
                'excerpt_pt'   => 'Quando esquemas declarativos não bastam — padrões para forms complexos com lógica condicional e estados intermediários.',
                'excerpt_en'   => 'When declarative schemas are not enough — patterns for complex forms with conditional logic and intermediate states.',
                'body_pt'      => "Filament resolve 80% dos forms com schema declarativo.\n\nO restante exige patterns que vou explorar aqui.",
                'body_en'      => "Filament handles 80% of forms with a declarative schema.\n\nThe rest needs patterns I will explore here.",
                'tags'         => ['Filament'],
                'reading_time' => 6,
                'published_at' => Carbon::parse('2025-11-21'),
            ],
            [
                'slug'         => 'migrando-filament-v3-para-v4',
                'title_pt'     => 'Migrando do Filament v3 para v4 sem quebrar produção',
                'title_en'     => 'Migrating from Filament v3 to v4 without breaking production',
                'excerpt_pt'   => 'Estratégia de migração gradual: shims, feature flags e como manter os dois rodando lado a lado durante semanas.',
                'excerpt_en'   => 'Gradual migration strategy: shims, feature flags and keeping both versions running side by side for weeks.',
                'body_pt'      => "Migração big-bang quase nunca é a resposta certa.\n\nEsta é a sequência que usei em 3 produtos.",
                'body_en'      => "A big-bang migration is almost never the right answer.\n\nThis is the sequence I used in 3 products.",
                'tags'         => ['Filament'],
                'reading_time' => 10,
                'published_at' => Carbon::parse('2025-11-08'),
            ],
            [
                'slug'         => 'pix-laravel-integracao-minima-viavel',
                'title_pt'     => 'PIX em Laravel: integração mínima viável com gateways BR',
                'title_en'     => 'PIX in Laravel: minimum viable integration with Brazilian gateways',
                'excerpt_pt'   => 'Wrapper sobre Asaas/Pagar.me com retry, webhook handler e idempotência. Código pronto para colar.',
                'excerpt_en'   => 'Wrapper over Asaas/Pagar.me with retry, webhook handler and idempotency. Copy-paste ready code.',
                'body_pt'      => "PIX é hoje o método de pagamento dominante no Brasil.\n\nVeja como implementar de forma idempotente.",
                'body_en'      => "PIX is now the dominant payment method in Brazil.\n\nHere is how to implement it idempotently.",
                'tags'         => ['Laravel', 'PHP'],
                'reading_time' => 5,
                'published_at' => Carbon::parse('2025-10-24'),
            ],
            [
                'slug'         => 'por-que-mantenho-20-plugins-filament',
                'title_pt'     => 'Por que mantenho 20+ plugins Filament',
                'title_en'     => 'Why I maintain 20+ Filament plugins',
                'excerpt_pt'   => 'A economia de manter pacotes pequenos open source enquanto trabalho em consultoria. Métricas reais e armadilhas.',
                'excerpt_en'   => 'The economics of maintaining small open source packages while doing consulting. Real metrics and pitfalls.',
                'body_pt'      => "Manter open source dá trabalho. Vou expor número por número o que isso significa.",
                'body_en'      => "Maintaining open source is work. I will lay out the numbers in detail.",
                'tags'         => ['Open Source'],
                'reading_time' => 15,
                'published_at' => Carbon::parse('2025-10-11'),
            ],
            [
                'slug'         => 'testes-pest-pacotes-laravel-setup-minimo',
                'title_pt'     => 'Testes Pest em pacotes Laravel: o setup mínimo',
                'title_en'     => 'Pest tests in Laravel packages: the minimum setup',
                'excerpt_pt'   => 'Orchestra Testbench + Pest + GitHub Actions. Configuração que reuso em todos os meus 35+ pacotes.',
                'excerpt_en'   => 'Orchestra Testbench + Pest + GitHub Actions. The setup I reuse across 35+ packages.',
                'body_pt'      => "Pacote sem teste é pacote condenado.\n\nVeja o boilerplate que adoto.",
                'body_en'      => "A package without tests is a doomed package.\n\nSee the boilerplate I adopt.",
                'tags'         => ['Open Source', 'Laravel', 'PHP'],
                'reading_time' => 7,
                'published_at' => Carbon::parse('2025-10-02'),
            ],
            [
                'slug'         => 'filament-actions-flows-multi-step',
                'title_pt'     => 'Filament Actions: pattern para flows multi-step',
                'title_en'     => 'Filament Actions: a pattern for multi-step flows',
                'excerpt_pt'   => 'Quando uma Action precisa virar um wizard com state intermediário — usando halts e mountedActions corretamente.',
                'excerpt_en'   => 'When an Action needs to become a wizard with intermediate state — using halts and mountedActions correctly.',
                'body_pt'      => "Actions são poderosas mas têm limites de UX.\n\nVamos resolver com halts e state carry-over.",
                'body_en'      => "Actions are powerful but have UX limits.\n\nLet us solve this with halts and state carry-over.",
                'tags'         => ['Filament'],
                'reading_time' => 9,
                'published_at' => Carbon::parse('2025-09-17'),
            ],
            [
                'slug'         => 'cep-cpf-cnpj-historia-filament-cep-field',
                'title_pt'     => 'CEP, CPF, CNPJ: por que existe filament-cep-field',
                'title_en'     => 'CEP, CPF, CNPJ: why filament-cep-field exists',
                'excerpt_pt'   => 'A história por trás do plugin mais baixado dos meus repos, e o que aprendi sobre validação de campos brasileiros.',
                'excerpt_en'   => 'The story behind my most downloaded plugin, and what I learned about validating Brazilian fields.',
                'body_pt'      => "Validar CPF/CNPJ tem armadilhas que pacotes internacionais ignoram.\n\nAqui está o porquê.",
                'body_en'      => "Validating CPF/CNPJ has pitfalls international packages ignore.\n\nHere is why.",
                'tags'         => ['Filament', 'Open Source'],
                'reading_time' => 6,
                'published_at' => Carbon::parse('2025-09-03'),
            ],
        ];
    }
}

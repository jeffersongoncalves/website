<?php

return [
    'navigation' => [
        'management' => 'Gestão',
        'user' => 'Usuário',
        'settings' => 'Configurações',
    ],

    'profile' => [
        'title' => 'Meu Perfil',
    ],

    'sections' => [
        'identity' => 'Identidade',
        'publication' => 'Publicação',
        'content' => 'Conteúdo',
        'stack_versions' => 'Stack & versões',
        'metrics' => 'Métricas',
        'links' => 'Links',
        'cover' => 'Capa',
        'description' => 'Descrição',
        'stack' => 'Stack',
    ],

    'fields' => [
        'name' => 'Nome',
        'email' => 'Email',
        'password' => 'Senha',
        'slug' => 'Slug',
        'repo' => 'Repositório',
        'category' => 'Categoria',
        'status' => 'Status',
        'featured' => 'Destacado',
        'sort_order' => 'Ordem',
        'published_at' => 'Publicado em',
        'title' => 'Título',
        'description' => 'Descrição',
        'content_markdown' => 'Conteúdo (Markdown)',
        'versions' => 'Versões',
        'stack' => 'Stack',
        'branch_overrides' => 'Branches',
        'auto_branch' => 'Branch auto',
        'real_branch' => 'Branch real no GitHub',
        'readme_branch' => 'Branch do README',
        'stars' => 'Estrelas',
        'downloads' => 'Downloads',
        'downloads_label' => 'Rótulo de downloads',
        'license' => 'Licença',
        'github_url' => 'URL do GitHub',
        'packagist_url' => 'URL do Packagist',
        'docs_url' => 'URL da documentação',
        'demo_url' => 'URL do demo',
        'cover_image' => 'Imagem de capa',
        'is_maintainer' => 'Apenas mantenedor',
    ],

    'helpers' => [
        'slug' => 'Deixe em branco para gerar automaticamente a partir do nome.',
        'repo' => 'Nome do repositório no GitHub (padrão: slug).',
        'featured' => 'Exibir na página inicial.',
        'is_maintainer' => 'Plugin que mantenho mas não criei. Exibe uma badge "mantenedor" no frontend.',
        'versions' => 'Branch padrão mapeia por índice: menor versão = 1.x, próxima = 2.x, etc. Sobrescreva por versão abaixo.',
        'branch_overrides' => 'Remapeia branches auto para as reais do repo (ex: `1.x → main`, `2.x → 2.x`). Vazio = usa o branch auto.',
        'readme_branch' => 'Branch do GitHub usada para buscar o README. Vazio = usa a branch padrão do repositório.',
        'downloads_label' => 'Valor exibido: 21k, 1.2M, —',
    ],

    'placeholders' => [
        'stack' => 'Laravel, Filament, Livewire',
        'versions_free' => 'Laravel 10/11/12, Filament v5',
    ],

    'enums' => [
        'category' => [
            'filament_plugin' => 'Plugin Filament',
            'laravel_package' => 'Pacote Laravel',
            'starter_kit' => 'Starter Kit',
            'saas' => 'SaaS',
            'tool' => 'Ferramenta',
        ],
        'status' => [
            'draft' => 'Rascunho',
            'published' => 'Publicado',
            'archived' => 'Arquivado',
        ],
    ],
];

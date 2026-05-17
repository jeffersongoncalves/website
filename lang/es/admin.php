<?php

return [
    'navigation' => [
        'management' => 'Gestión',
        'user' => 'Usuario',
        'settings' => 'Configuración',
    ],

    'profile' => [
        'title' => 'Mi Perfil',
    ],

    'login' => [
        'eyebrow' => 'acceso restringido',
        'whoami_unauth' => 'guest · no autenticado',
        'secure_connection' => 'conexión segura',
        'back_to_site' => 'volver al sitio',
    ],

    'status' => [
        'label' => 'estado',
        'production' => 'producción',
        'local' => 'local',
    ],

    'widgets' => [
        'site_metrics' => [
            'heading' => 'Métricas del sitio',
            'repos' => 'Repositorios',
            'repos_desc' => 'proyectos publicados',
            'stars' => 'Estrellas',
            'stars_desc' => 'sumadas en GitHub',
            'downloads' => 'Descargas',
            'downloads_desc' => 'Packagist',
            'filament' => 'Plugins Filament',
            'filament_desc' => 'categoría',
            'laravel' => 'Paquetes Laravel',
            'laravel_desc' => 'categoría',
            'starter' => 'Starter Kits',
            'starter_desc' => 'categoría',
            'maintained' => 'Mantenedor',
            'maintained_desc' => 'proyectos que mantengo pero no creé',
            'followers' => 'Seguidores',
            'followers_desc' => 'en GitHub',
            'sponsors' => 'Sponsors',
            'sponsors_desc' => 'públicos',
            'contributions' => 'Contribuciones',
            'contributions_desc' => 'últimos 12 meses',
        ],
    ],

    'sections' => [
        'identity' => 'Identidad',
        'publication' => 'Publicación',
        'title' => 'Título',
        'content' => 'Contenido',
        'stack_versions' => 'Stack y versiones',
        'metrics' => 'Métricas',
        'links' => 'Enlaces',
        'cover' => 'Portada',
        'description' => 'Descripción',
        'stack' => 'Stack',
    ],

    'fields' => [
        'name' => 'Nombre',
        'email' => 'Correo electrónico',
        'password' => 'Contraseña',
        'slug' => 'Slug',
        'repo' => 'Repositorio',
        'category' => 'Categoría',
        'status' => 'Estado',
        'featured' => 'Destacado',
        'sort_order' => 'Orden',
        'published_at' => 'Publicado el',
        'title' => 'Título',
        'description' => 'Descripción',
        'content_markdown' => 'Contenido (Markdown)',
        'versions' => 'Versiones',
        'stack' => 'Stack',
        'branch_overrides' => 'Ramas',
        'auto_branch' => 'Rama automática',
        'real_branch' => 'Rama real de GitHub',
        'readme_branch' => 'Rama del README',
        'stars' => 'Estrellas',
        'downloads' => 'Descargas',
        'downloads_label' => 'Etiqueta de descargas',
        'license' => 'Licencia',
        'github_url' => 'URL de GitHub',
        'packagist_url' => 'URL de Packagist',
        'docs_url' => 'URL de la documentación',
        'demo_url' => 'URL del demo',
        'cover_image' => 'Imagen de portada',
        'is_maintainer' => 'Solo mantenedor',
        'created_at' => 'Creado el',
        'updated_at' => 'Actualizado el',
    ],

    'helpers' => [
        'slug' => 'Deja en blanco para generar automáticamente desde el nombre.',
        'repo' => 'Nombre del repo en GitHub (por defecto: slug).',
        'featured' => 'Mostrar en la página de inicio.',
        'is_maintainer' => 'Plugin que mantengo pero no creé. Muestra una insignia "mantenedor" en el frontend.',
        'versions' => 'Rama por defecto se mapea por índice: versión menor = 1.x, siguiente = 2.x, etc. Sobrescribe por versión abajo.',
        'branch_overrides' => 'Remapea ramas automáticas a las reales del repo, ej: `1.x → main`, `2.x → 2.x`. Deja en blanco para usar la rama automática.',
        'readme_branch' => 'Rama de GitHub usada para obtener el README. Deja en blanco para usar la rama por defecto del repositorio.',
        'downloads_label' => 'Valor mostrado: 21k, 1.2M, —',
    ],

    'placeholders' => [
        'stack' => 'Laravel, Filament, Livewire',
        'versions_free' => 'Laravel 10/11/12, Filament v5',
    ],

    'enums' => [
        'category' => [
            'filament_plugin' => 'Plugin Filament',
            'laravel_package' => 'Paquete Laravel',
            'starter_kit' => 'Starter Kit',
            'saas' => 'SaaS',
            'tool' => 'Herramienta',
        ],
        'status' => [
            'draft' => 'Borrador',
            'published' => 'Publicado',
            'archived' => 'Archivado',
        ],
    ],
];

<?php

namespace Database\Seeders;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    private const FEATURED_SLUGS = [
        'filakitv5',
        'filament-help-desk',
        'filament-cep-field',
        'filament-documentation',
        'teamkitv5',
        'filament-service-desk',
    ];

    public function run(): void
    {
        $order = 0;

        foreach ($this->projects() as $row) {
            $slug = $row['repo'];

            Project::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name'            => $row['name'],
                    'repo'            => $row['repo'],
                    'category'        => $row['category']->value,
                    'description'     => [
                        'pt' => $row['desc_pt'],
                        'en' => $row['desc_en'] ?? $row['desc_pt'],
                    ],
                    'versions'        => $row['versions'],
                    'stack'           => $row['stack'],
                    'stars'           => $row['stars'],
                    'downloads'       => $this->parseDownloads($row['dl']),
                    'downloads_label' => $row['dl'],
                    'license'         => $row['license'] ?? 'MIT',
                    'status'          => ProjectStatus::Published->value,
                    'featured'        => in_array($slug, self::FEATURED_SLUGS, true),
                    'sort_order'      => $order++,
                    'published_at'    => now()->subDays(random_int(30, 720)),
                ]
            );
        }
    }

    private function parseDownloads(string $label): int
    {
        $label = trim(strtolower($label));

        if ($label === '' || $label === '—' || $label === '-') {
            return 0;
        }

        $multiplier = 1;
        if (str_ends_with($label, 'k')) {
            $multiplier = 1_000;
            $label = rtrim($label, 'k');
        } elseif (str_ends_with($label, 'm')) {
            $multiplier = 1_000_000;
            $label = rtrim($label, 'm');
        }

        return (int) round(((float) $label) * $multiplier);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function projects(): array
    {
        return [
            // ────────── Filament Plugins (26) ──────────
            ['name' => 'filament-mixpanel', 'repo' => 'filament-mixpanel', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Integração Mixpanel para painéis Filament: tracking de eventos, properties e identificação de usuários.',
             'desc_en' => 'Mixpanel integration for Filament panels: event tracking, properties and user identification.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'Mixpanel'], 'stars' => 42, 'dl' => '8.2k'],

            ['name' => 'filament-documentation', 'repo' => 'filament-documentation', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Página de documentação Markdown integrada ao painel Filament, com sidebar e busca.',
             'desc_en' => 'Markdown documentation page integrated into the Filament panel, with sidebar and search.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'Markdown'], 'stars' => 89, 'dl' => '24k'],

            ['name' => 'filament-knowledge-base', 'repo' => 'filament-knowledge-base', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Base de conhecimento navegável para usuários finais ou equipe interna, dentro do Filament.',
             'desc_en' => 'Navigable knowledge base for end users or internal teams, inside Filament.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'Livewire'], 'stars' => 67, 'dl' => '11k'],

            ['name' => 'filament-satis', 'repo' => 'filament-satis', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Gerencie um repositório Composer Satis privado direto pelo Filament. Útil para empresas com pacotes próprios.',
             'desc_en' => 'Manage a private Composer Satis repository directly from Filament. Useful for companies with proprietary packages.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'Composer'], 'stars' => 54, 'dl' => '6.4k'],

            ['name' => 'filament-help-desk', 'repo' => 'filament-help-desk', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Sistema de help desk completo dentro do Filament: tickets, prioridades, atribuições e SLA.',
             'desc_en' => 'Complete help desk system inside Filament: tickets, priorities, assignments and SLA.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament'], 'stars' => 112, 'dl' => '18k'],

            ['name' => 'filament-service-desk', 'repo' => 'filament-service-desk', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Service desk com SLA e categorização avançada. Extensão do help-desk para times maiores.',
             'desc_en' => 'Service desk with SLA and advanced categorization. Help-desk extension for larger teams.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament'], 'stars' => 78, 'dl' => '9.1k'],

            ['name' => 'filament-refresh-sidebar', 'repo' => 'filament-refresh-sidebar', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Recarrega a sidebar do Filament dinamicamente após mudanças no menu, sem refresh da página.',
             'desc_en' => 'Dynamically reloads the Filament sidebar after menu changes, without page refresh.',
             'versions' => ['v4', 'v5'], 'stack' => ['Filament', 'Livewire'], 'stars' => 36, 'dl' => '14k'],

            ['name' => 'filament-hidden-action', 'repo' => 'filament-hidden-action', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Action invisível para Filament — útil para gatilhos por keybinding ou execução programática.',
             'desc_en' => 'Invisible Action for Filament — useful for keybinding triggers or programmatic execution.',
             'versions' => ['v4', 'v5'], 'stack' => ['Filament'], 'stars' => 28, 'dl' => '5.7k'],

            ['name' => 'filament-multifactor-whatsapp', 'repo' => 'filament-multifactor-whatsapp', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Autenticação multi-fator via WhatsApp. Provider para o sistema 2FA do Filament v4+.',
             'desc_en' => 'Multi-factor authentication via WhatsApp. Provider for the Filament v4+ 2FA system.',
             'versions' => ['v4', 'v5'], 'stack' => ['Filament', 'WhatsApp'], 'stars' => 64, 'dl' => '3.2k'],

            ['name' => 'filament-ace-editor-field', 'repo' => 'filament-ace-editor-field', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Field do Filament com editor Ace embutido: syntax highlighting para 100+ linguagens.',
             'desc_en' => 'Filament field with embedded Ace editor: syntax highlighting for 100+ languages.',
             'versions' => ['v4', 'v5'], 'stack' => ['Filament', 'Ace'], 'stars' => 48, 'dl' => '7.8k'],

            ['name' => 'filament-topbar', 'repo' => 'filament-topbar', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Topbar customizável para Filament com slots configuráveis: busca, notificações, atalhos.',
             'desc_en' => 'Customizable topbar for Filament with configurable slots: search, notifications, shortcuts.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament'], 'stars' => 71, 'dl' => '12k'],

            ['name' => 'filament-keyable', 'repo' => 'filament-keyable', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Atribui chaves UUID ou slug curtos a recursos Filament para URLs amigáveis.',
             'desc_en' => 'Assigns UUIDs or short slugs to Filament resources for friendly URLs.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'UUID'], 'stars' => 39, 'dl' => '6.9k'],

            ['name' => 'filament-logo', 'repo' => 'filament-logo', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Helper para gerenciar logos do painel Filament (light/dark/favicon) via config simples.',
             'desc_en' => 'Helper to manage Filament panel logos (light/dark/favicon) via simple config.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament'], 'stars' => 33, 'dl' => '15k'],

            ['name' => 'filament-cep-field', 'repo' => 'filament-cep-field', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Field de CEP brasileiro com auto-preenchimento de endereço via ViaCEP.',
             'desc_en' => 'Brazilian ZIP code (CEP) field with auto-fill address via ViaCEP.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'BR'], 'stars' => 95, 'dl' => '21k'],

            ['name' => 'filament-qrcode-field', 'repo' => 'filament-qrcode-field', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Field do Filament que renderiza um QR Code em tempo real do conteúdo digitado.',
             'desc_en' => 'Filament field that renders a real-time QR Code from typed content.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'QR'], 'stars' => 56, 'dl' => '8.8k'],

            ['name' => 'filament-one-time-operations', 'repo' => 'filament-one-time-operations', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Wrapper Filament para o pacote spatie/laravel-one-time-operations: gerencie pelo painel.',
             'desc_en' => 'Filament wrapper for the spatie/laravel-one-time-operations package: manage via panel.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'Spatie'], 'stars' => 58, 'dl' => '7.2k'],

            ['name' => 'filament-whatsapp-widget', 'repo' => 'filament-whatsapp-widget', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Widget de chat WhatsApp para o painel Filament — links diretos para conversa.',
             'desc_en' => 'WhatsApp chat widget for the Filament panel — direct conversation links.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'WhatsApp'], 'stars' => 41, 'dl' => '9.4k'],

            ['name' => 'filament-cookie-consent', 'repo' => 'filament-cookie-consent', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Banner de cookies LGPD/GDPR configurável para sites baseados em Filament.',
             'desc_en' => 'Configurable LGPD/GDPR cookie banner for Filament-based sites.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'LGPD'], 'stars' => 73, 'dl' => '13k'],

            ['name' => 'filament-check-whois-widget', 'repo' => 'filament-check-whois-widget', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Widget de consulta WHOIS de domínios direto no dashboard Filament.',
             'desc_en' => 'WHOIS domain lookup widget directly on the Filament dashboard.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'WHOIS'], 'stars' => 22, 'dl' => '2.1k'],

            ['name' => 'filament-umami', 'repo' => 'filament-umami', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Integração Umami Analytics para dashboards Filament. Privacidade-first.',
             'desc_en' => 'Umami Analytics integration for Filament dashboards. Privacy-first.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'Umami'], 'stars' => 47, 'dl' => '6.1k'],

            ['name' => 'filament-plausible', 'repo' => 'filament-plausible', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Plausible Analytics dentro do Filament — métricas leves e GDPR-friendly.',
             'desc_en' => 'Plausible Analytics inside Filament — lightweight, GDPR-friendly metrics.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'Plausible'], 'stars' => 51, 'dl' => '7.5k'],

            ['name' => 'filament-matomo', 'repo' => 'filament-matomo', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Tracking Matomo configurável via Filament panel — analytics open source self-hosted.',
             'desc_en' => 'Matomo tracking configurable via Filament panel — self-hosted open source analytics.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'Matomo'], 'stars' => 29, 'dl' => '3.4k'],

            ['name' => 'filament-pixel', 'repo' => 'filament-pixel', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Facebook/Meta Pixel integrado ao Filament: tracking de conversões e eventos.',
             'desc_en' => 'Facebook/Meta Pixel integrated with Filament: conversion and event tracking.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'Meta'], 'stars' => 35, 'dl' => '5.8k'],

            ['name' => 'filament-gtag', 'repo' => 'filament-gtag', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Google Analytics gtag.js gerenciado pelo Filament — sem editar templates.',
             'desc_en' => 'Google Analytics gtag.js managed by Filament — no template editing.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'GA'], 'stars' => 44, 'dl' => '11k'],

            ['name' => 'filament-gtm', 'repo' => 'filament-gtm', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Google Tag Manager para Filament com configuração via panel UI.',
             'desc_en' => 'Google Tag Manager for Filament with panel UI configuration.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'GTM'], 'stars' => 38, 'dl' => '8.6k'],

            ['name' => 'filament-fathom', 'repo' => 'filament-fathom', 'category' => ProjectCategory::FilamentPlugin,
             'desc_pt' => 'Fathom Analytics para Filament: tracking simples, GDPR-compliant.',
             'desc_en' => 'Fathom Analytics for Filament: simple tracking, GDPR-compliant.',
             'versions' => ['v3', 'v4', 'v5'], 'stack' => ['Filament', 'Fathom'], 'stars' => 26, 'dl' => '2.8k'],

            // ────────── Laravel Packages (11) ──────────
            ['name' => 'laravel-mixpanel', 'repo' => 'laravel-mixpanel', 'category' => ProjectCategory::LaravelPackage,
             'desc_pt' => 'Cliente Mixpanel para Laravel com facade, queue jobs e identificação automática de usuários.',
             'desc_en' => 'Mixpanel client for Laravel with facade, queue jobs and automatic user identification.',
             'versions' => ['Laravel 10/11/12'], 'stack' => ['Laravel', 'Mixpanel'], 'stars' => 38, 'dl' => '14k'],

            ['name' => 'laravel-knowledge-base', 'repo' => 'laravel-knowledge-base', 'category' => ProjectCategory::LaravelPackage,
             'desc_pt' => 'Pacote standalone de base de conhecimento para Laravel (sem dependência de Filament).',
             'desc_en' => 'Standalone Laravel knowledge base package (no Filament dependency).',
             'versions' => ['Laravel 10/11/12'], 'stack' => ['Laravel'], 'stars' => 31, 'dl' => '5.2k'],

            ['name' => 'laravel-satis', 'repo' => 'laravel-satis', 'category' => ProjectCategory::LaravelPackage,
             'desc_pt' => 'Gerenciamento de Satis (Composer) via Artisan — sincronização automatizada de pacotes.',
             'desc_en' => 'Satis (Composer) management via Artisan — automated package synchronization.',
             'versions' => ['Laravel 10/11/12'], 'stack' => ['Laravel', 'Composer'], 'stars' => 27, 'dl' => '3.9k'],

            ['name' => 'laravel-help-desk', 'repo' => 'laravel-help-desk', 'category' => ProjectCategory::LaravelPackage,
             'desc_pt' => 'Backend de help desk reutilizável: models, migrations, policies. UI agnóstica.',
             'desc_en' => 'Reusable help desk backend: models, migrations, policies. UI-agnostic.',
             'versions' => ['Laravel 10/11/12'], 'stack' => ['Laravel'], 'stars' => 54, 'dl' => '9.7k'],

            ['name' => 'laravel-service-desk', 'repo' => 'laravel-service-desk', 'category' => ProjectCategory::LaravelPackage,
             'desc_pt' => 'Camada de service desk com SLA, escalonamento e métricas. Inclui eventos e listeners.',
             'desc_en' => 'Service desk layer with SLA, escalation and metrics. Includes events and listeners.',
             'versions' => ['Laravel 10/11/12'], 'stack' => ['Laravel'], 'stars' => 33, 'dl' => '4.6k'],

            ['name' => 'laravel-fake-cartoons', 'repo' => 'laravel-fake-cartoons', 'category' => ProjectCategory::LaravelPackage,
             'desc_pt' => 'Faker provider que gera nomes de personagens de desenhos animados — útil para seeders divertidos.',
             'desc_en' => 'Faker provider that generates cartoon character names — useful for fun seeders.',
             'versions' => ['Laravel 10/11/12'], 'stack' => ['Laravel', 'Faker'], 'stars' => 19, 'dl' => '12k'],

            ['name' => 'laravel-whatsapp-widget', 'repo' => 'laravel-whatsapp-widget', 'category' => ProjectCategory::LaravelPackage,
             'desc_pt' => 'Widget Blade de WhatsApp para sites Laravel com configuração via env.',
             'desc_en' => 'WhatsApp Blade widget for Laravel sites with env-based configuration.',
             'versions' => ['Laravel 10/11/12'], 'stack' => ['Laravel', 'Blade'], 'stars' => 36, 'dl' => '8.1k'],

            ['name' => 'laravel-cookie-consent', 'repo' => 'laravel-cookie-consent', 'category' => ProjectCategory::LaravelPackage,
             'desc_pt' => 'Banner de consentimento de cookies para Laravel — LGPD compliant, sem JS pesado.',
             'desc_en' => 'Cookie consent banner for Laravel — LGPD compliant, no heavy JS.',
             'versions' => ['Laravel 10/11/12'], 'stack' => ['Laravel', 'LGPD'], 'stars' => 62, 'dl' => '17k'],

            ['name' => 'laravel-created-by', 'repo' => 'laravel-created-by', 'category' => ProjectCategory::LaravelPackage,
             'desc_pt' => 'Trait para preencher created_by/updated_by automaticamente em models Laravel.',
             'desc_en' => 'Trait to auto-fill created_by/updated_by on Laravel models.',
             'versions' => ['Laravel 10/11/12'], 'stack' => ['Laravel', 'Eloquent'], 'stars' => 45, 'dl' => '22k'],

            ['name' => 'laravel-umami', 'repo' => 'laravel-umami', 'category' => ProjectCategory::LaravelPackage,
             'desc_pt' => 'Cliente PHP para Umami Analytics — server-side tracking de conversões.',
             'desc_en' => 'PHP client for Umami Analytics — server-side conversion tracking.',
             'versions' => ['Laravel 10/11/12'], 'stack' => ['Laravel', 'Umami'], 'stars' => 24, 'dl' => '4.3k'],

            ['name' => 'laravel-plausible', 'repo' => 'laravel-plausible', 'category' => ProjectCategory::LaravelPackage,
             'desc_pt' => 'Helpers Plausible para Laravel: blade directives e API client.',
             'desc_en' => 'Plausible helpers for Laravel: blade directives and API client.',
             'versions' => ['Laravel 10/11/12'], 'stack' => ['Laravel', 'Plausible'], 'stars' => 31, 'dl' => '5.9k'],

            // ────────── Starter Kits (7) ──────────
            ['name' => 'filakitv5', 'repo' => 'filakitv5', 'category' => ProjectCategory::StarterKit,
             'desc_pt' => 'Starter kit Laravel + Filament v5 com tudo pré-configurado: auth, multi-tenancy, billing.',
             'desc_en' => 'Laravel + Filament v5 starter kit with everything preconfigured: auth, multi-tenancy, billing.',
             'versions' => ['Filament v5'], 'stack' => ['Laravel', 'Filament'], 'stars' => 124, 'dl' => '6.8k'],

            ['name' => 'nativekitv5', 'repo' => 'nativekitv5', 'category' => ProjectCategory::StarterKit,
             'desc_pt' => 'Kit base para apps Native PHP com Filament — desktop apps usando seu stack PHP.',
             'desc_en' => 'Base kit for Native PHP apps with Filament — desktop apps using your PHP stack.',
             'versions' => ['Filament v5', 'Native PHP'], 'stack' => ['NativePHP'], 'stars' => 67, 'dl' => '2.1k'],

            ['name' => 'mobilekitv5', 'repo' => 'mobilekitv5', 'category' => ProjectCategory::StarterKit,
             'desc_pt' => 'Kit Laravel + Capacitor para apps híbridos com painel Filament centralizado.',
             'desc_en' => 'Laravel + Capacitor kit for hybrid apps with a centralized Filament panel.',
             'versions' => ['Filament v5', 'Capacitor'], 'stack' => ['Laravel', 'Capacitor'], 'stars' => 42, 'dl' => '1.8k'],

            ['name' => 'teamkitv5', 'repo' => 'teamkitv5', 'category' => ProjectCategory::StarterKit,
             'desc_pt' => 'Multi-team / multi-tenant Laravel + Filament — convites, roles e billing por team.',
             'desc_en' => 'Multi-team / multi-tenant Laravel + Filament — invites, roles and per-team billing.',
             'versions' => ['Filament v5'], 'stack' => ['Laravel', 'Filament'], 'stars' => 89, 'dl' => '3.4k'],

            ['name' => 'servicedeskkitv5', 'repo' => 'servicedeskkitv5', 'category' => ProjectCategory::StarterKit,
             'desc_pt' => 'Service desk pronto: tickets, SLA, base de conhecimento e dashboard. Plug-and-play.',
             'desc_en' => 'Ready-to-use service desk: tickets, SLA, knowledge base and dashboard. Plug-and-play.',
             'versions' => ['Filament v5'], 'stack' => ['Laravel', 'Filament'], 'stars' => 56, 'dl' => '1.9k'],

            ['name' => 'evolutionkitv4', 'repo' => 'evolutionkitv4', 'category' => ProjectCategory::StarterKit,
             'desc_pt' => 'Integração Evolution API (WhatsApp) com Laravel + Filament v4 — bots e atendimento.',
             'desc_en' => 'Evolution API (WhatsApp) integration with Laravel + Filament v4 — bots and support.',
             'versions' => ['Filament v4', 'Evolution'], 'stack' => ['Laravel', 'WhatsApp'], 'stars' => 78, 'dl' => '2.7k'],

            ['name' => 'mfakitv4', 'repo' => 'mfakitv4', 'category' => ProjectCategory::StarterKit,
             'desc_pt' => 'Multi-factor auth completo para Filament v4: TOTP, e-mail, SMS, WhatsApp.',
             'desc_en' => 'Complete multi-factor auth for Filament v4: TOTP, email, SMS, WhatsApp.',
             'versions' => ['Filament v4'], 'stack' => ['Laravel', 'MFA'], 'stars' => 51, 'dl' => '2.2k'],
        ];
    }
}

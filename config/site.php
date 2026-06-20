<?php

declare(strict_types=1);

return [
    'social' => [
        'github' => 'https://github.com/jeffersongoncalves',
        'linkedin' => 'https://www.linkedin.com/in/jeffersonsimaogoncalves/',
        'packagist' => 'https://packagist.org/packages/jeffersongoncalves/',
        'x' => 'https://x.com/gersonsimao92',
        'email' => 'contato@jeffersongoncalves.dev.br',
        'sponsors' => 'https://github.com/sponsors/jeffersongoncalves',
    ],

    // home_stats and os_stats are now computed dynamically by App\Support\SiteStats.

    // Live demo subdomains of the main site. Each entry is a Laravel/Filament
    // starter kit running its own instance at <slug>.jeffersongoncalves.dev.br
    // so visitors can poke a real install before adopting the kit. Rendered
    // by site header's Demos dropdown.
    'demos' => [
        ['label' => 'Plugins Showcase', 'url' => 'https://demo.jeffersongoncalves.dev.br'],
        ['label' => 'TeamKit', 'url' => 'https://teamkit.jeffersongoncalves.dev.br'],
        ['label' => 'ServiceDeskKit', 'url' => 'https://servicedeskkit.jeffersongoncalves.dev.br'],
        ['label' => 'HelpDeskKit', 'url' => 'https://helpdeskkit.jeffersongoncalves.dev.br'],
        ['label' => 'FilaKit', 'url' => 'https://filakit.jeffersongoncalves.dev.br'],
        ['label' => 'EvolutionKit', 'url' => 'https://evolutionkit.jeffersongoncalves.dev.br'],
        ['label' => 'FilaFluxKit', 'url' => 'https://filafluxkit.jeffersongoncalves.dev.br'],
        ['label' => 'MfaKit', 'url' => 'https://mfakit.jeffersongoncalves.dev.br'],
    ],

    'stack' => [
        ['label' => 'Laravel'],
        ['label' => 'Filament'],
        ['label' => 'PHP'],
        ['label' => 'Livewire'],
        ['label' => 'Alpine.js'],
        ['label' => 'Tailwind'],
        ['label' => 'PostgreSQL'],
        ['label' => 'Redis'],
    ],

    'timeline' => [
        [
            'year_short' => '2025',
            'year_long' => '2025',
            'title_pt' => 'Grupo Nexus · Desenvolvedor Full Stack',
            'title_en' => 'Grupo Nexus · Full Stack Developer',
            'period_pt' => 'jul–set 2025 · Assis, SP',
            'period_en' => 'Jul–Sep 2025 · Assis, SP',
            'desc_pt' => 'Sistema de gestão de fundos de investimentos com gestão de contratos e distribuição de rentabilidades.',
            'desc_en' => 'Investment fund management system with contracts and yield distribution.',
            'stack' => ['AdonisJS', 'ReactJS'],
            'accent' => true,
        ],
        [
            'year_short' => '2020–25',
            'year_long' => '2020–2025',
            'title_pt' => 'BRG AGN · Desenvolvedor Full Stack',
            'title_en' => 'BRG AGN · Full Stack Developer',
            'period_pt' => 'set 2020 – jun 2025 · 4 anos 10 meses · Tarumã, SP',
            'period_en' => 'Sep 2020 – Jun 2025 · 4 yr 10 mo · Tarumã, SP',
            'desc_pt' => 'Sistema completo de levantamento e gestão de ativos: impressão de etiquetas RFID em impressoras Zebra, integração Alien RFID e app Android para inventário físico com geolocalização.',
            'desc_en' => 'Complete asset survey and management system: RFID label printing on Zebra printers, Alien RFID integration and Android app for physical inventory with geolocation.',
            'stack' => ['CakePHP', 'MariaDB', 'Android', 'RFID'],
        ],
        [
            'year_short' => '2025',
            'year_long' => '2025',
            'title_pt' => 'Sistema Boss · Desenvolvedor Full Stack',
            'title_en' => 'Sistema Boss · Full Stack Developer',
            'period_pt' => 'jan–mai 2025 · Brasília, DF',
            'period_en' => 'Jan–May 2025 · Brasília, DF',
            'desc_pt' => 'Plataforma para gerenciamento de eventos: credenciamento, impressão de crachás, relatórios de acesso e app React Native para validar entrada via QR Code.',
            'desc_en' => 'Event management platform: check-in, badge printing, access reports and React Native app for QR Code entry validation.',
            'stack' => ['Laravel', 'Livewire', 'Filament', 'Laravel Nova', 'React Native'],
        ],
        [
            'year_short' => '2021–24',
            'year_long' => '2021–2024',
            'title_pt' => 'SISTERS Live Marketing · Desenvolvedor Full Stack',
            'title_en' => 'SISTERS Live Marketing · Full Stack Developer',
            'period_pt' => 'mar 2021 – dez 2024 · 3 anos 10 meses · Brasília, DF',
            'period_en' => 'Mar 2021 – Dec 2024 · 3 yr 10 mo · Brasília, DF',
            'desc_pt' => 'Sites e plataformas web para gerenciamento de eventos presenciais e online: credenciamento, impressão térmica de crachás, controle de entrada por área e app mobile React Native.',
            'desc_en' => 'Websites and web platforms for in-person and online event management: check-in, thermal badge printing, area entry control and React Native mobile app.',
            'stack' => ['Laravel', 'Livewire', 'Filament', 'Blade', 'React Native'],
        ],
        [
            'year_short' => '2014–20',
            'year_long' => '2014–2020',
            'title_pt' => 'Alpha Mobility · Desenvolvedor Full Stack',
            'title_en' => 'Alpha Mobility · Full Stack Developer',
            'period_pt' => 'out 2014 – ago 2020 · 5 anos 11 meses · Assis, SP',
            'period_en' => 'Oct 2014 – Aug 2020 · 5 yr 11 mo · Assis, SP',
            'desc_pt' => 'Sistema completo de gestão de ativos com CakePHP + MariaDB. Impressão RFID Zebra, integração Alien RFID e app Android para inventário em campo.',
            'desc_en' => 'Complete asset management system with CakePHP + MariaDB. Zebra RFID printing, Alien RFID integration and Android app for field inventory.',
            'stack' => ['CakePHP', 'MariaDB', 'Android (Java/Kotlin)', 'RFID'],
        ],
        [
            'year_short' => '2012–14',
            'year_long' => '2012–2014',
            'title_pt' => 'ComunicarTI · Desenvolvedor de Software',
            'title_en' => 'ComunicarTI · Software Developer',
            'period_pt' => 'mai 2012 – out 2014 · 2 anos 6 meses · Assis, SP',
            'period_en' => 'May 2012 – Oct 2014 · 2 yr 6 mo · Assis, SP',
            'desc_pt' => 'Sistema de gerenciamento de relatórios para antenas de telecomunicações e portal de notícias. Trabalho com múltiplas stacks: Flex, C#, PHP, Python e Android.',
            'desc_en' => 'Telecom antenna report management system and news portal. Multi-stack work: Flex, C#, PHP, Python and Android.',
            'stack' => ['PHP', 'C#', 'Python', 'Adobe Flex', 'Android (Java)'],
        ],
    ],

    'education' => [
        [
            'period' => '2011 — 2015',
            'title_pt' => 'Bacharelado em Ciências da Computação',
            'title_en' => 'Bachelor of Computer Science',
            'school' => 'FEMA — Fundação Educacional do Município de Assis',
        ],
        [
            'period' => '2007 — 2009',
            'title_pt' => 'Ensino Técnico — Tecnologia da Informação',
            'title_en' => 'Technical Education — Information Technology',
            'school' => 'Etec Prof. Luiz Pires Barbosa',
        ],
    ],

    'principles' => [
        [
            'title_pt' => 'Pacotes pequenos, propósitos claros.',
            'title_en' => 'Small packages, clear purposes.',
            'desc_pt' => 'Cada plugin resolve um problema específico. Componibilidade > monólitos opinionados.',
            'desc_en' => 'Each plugin solves one specific problem. Composability > opinionated monoliths.',
        ],
        [
            'title_pt' => 'DX é UX para devs.',
            'title_en' => 'DX is UX for developers.',
            'desc_pt' => 'Documentação clara, exemplos copy-paste, defaults sensatos. Se demora 30min para usar, está mal feito.',
            'desc_en' => 'Clear docs, copy-paste examples, sensible defaults. If it takes 30 minutes to use, it is poorly made.',
        ],
        [
            'title_pt' => 'Suporte às versões em paralelo.',
            'title_en' => 'Parallel version support.',
            'desc_pt' => 'Filament v3, v4 e v5 todos mantidos. Quem está em produção não pode ser obrigado a migrar no meu ritmo.',
            'desc_en' => 'Filament v3, v4 and v5 all maintained. Production users cannot be forced to migrate on my schedule.',
        ],
        [
            'title_pt' => 'Mercado brasileiro como foco.',
            'title_en' => 'Brazilian market as focus.',
            'desc_pt' => 'CEP, CPF/CNPJ, PIX, LGPD, fuso BRT. Coisas óbvias para nós que pacotes internacionais ignoram.',
            'desc_en' => 'CEP, CPF/CNPJ, PIX, LGPD, BRT timezone. Obvious things to us that international packages ignore.',
        ],
    ],

];

# jeffersongoncalves.dev.br

[![Tests](https://github.com/jeffersongoncalves/website/actions/workflows/tests.yml/badge.svg)](https://github.com/jeffersongoncalves/website/actions/workflows/tests.yml)
[![PHPStan](https://github.com/jeffersongoncalves/website/actions/workflows/phpstan.yml/badge.svg)](https://github.com/jeffersongoncalves/website/actions/workflows/phpstan.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Source code of [jeffersongoncalves.dev.br](https://jeffersongoncalves.dev.br) — the personal
portfolio of **Jefferson Gonçalves**, Full Stack PHP Developer. A multi-language site
(PT / EN / ES) that showcases projects, open-source packages, articles and sponsors, managed
through a Filament admin panel.

It is also a real-world showcase of many of my own open-source Laravel and Filament packages
working together — see the `/stack` page for the full list.

## Stack

- **PHP 8.4** / **Laravel 13** / **Filament 5** / **Livewire 4** / **Tailwind CSS 4**
- **Laravel Horizon** (Redis) for queues
- **Pest 4** + **PHPStan** (Larastan, level 7) + **Pint**
- Built on the [FilaKit v5](https://github.com/jeffersongoncalves/filakitv5) starter kit

## Features

- Localized public site (`/{locale}` with `pt_BR`, `en`, `es`): home, about, projects,
  articles, links, open-source, stack, sponsors and an MCP guide for developers
- Project pages rendering GitHub READMEs server-side (CommonMark + syntax highlighting,
  sanitized against XSS)
- GitHub contributions heatmap and site stats synced from the GitHub API via queued jobs
- Generated `sitemap.xml`, `llms.txt`, Open Graph images and an RSS feed for articles
- PWA: manifest, favicons and an offline-capable service worker
- Full-page cache, security headers and SSRF-guarded outbound fetches
- Filament admin panel (`/admin`) and Horizon dashboard (`/horizon`), both behind the `admin` guard

## Local development

Requirements: PHP 8.4, Composer, [Bun](https://bun.sh), Redis (only for Horizon).

Start a new project from this template:

```bash
composer create-project jeffersongoncalves/website my-site
```

Or from a clone:

```bash
composer install
bun install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
bun run build
```

Run the full dev stack (serve + queue + vite):

```bash
composer dev
```

## Quality

```bash
composer phpstan   # static analysis (Larastan, level 7)
composer pint      # code style
./vendor/bin/pest  # tests
```

## Production notes

`public/build` is not committed — the deploy runs `bun install && bun run build`. Production runs on PostgreSQL and needs Redis with `QUEUE_CONNECTION=redis`
(Horizon). Daily offsite backups (`.env` + database dump) go to Google Drive via
`spatie/laravel-backup` and
[`jeffersongoncalves/flysystem-google-drive`](https://github.com/jeffersongoncalves/flysystem-google-drive).

## Security

Found a vulnerability? Please don't open a public issue — report it privately through
[GitHub Security Advisories](https://github.com/jeffersongoncalves/website/security/advisories/new).

## License

The source code is open-sourced under the [MIT license](LICENSE). Site content (texts,
articles, images and branding) remains © Jefferson Gonçalves.

# jeffersongoncalves.dev.br

Personal portfolio and developer site of **Jefferson Gonçalves** — Full Stack PHP Developer.
Multi-language site (PT / EN / ES) presenting projects, open-source work, and sponsors,
backed by Filament admin panels.

## Stack

- **PHP 8.3+** / **Laravel 13** / **Filament 5** / **Livewire 4** / **Tailwind CSS 4**
- **Laravel Horizon** for Redis queue management
- Built on **FilaKit v5** (`jeffersongoncalves/filakitv5`)

## Features

- Public site with localized routes (`/pt`, `/en`, `/es`): home, about, projects, open-source, sponsors
- Real GitHub contributions heatmap and dynamic site stats from live data
- Project pages rendering README content
- Three Filament panels — Admin (`/admin`), App (`/app`), Guest (`/`) — each independently toggleable via `config/filakit.php`
- Two auth guards (`admin`, `web`) with separate user models
- Horizon dashboard at `/horizon`, authorized via the `admin` guard
- Dockerized production image with nginx + php-fpm + Horizon + scheduler

## Local development

```bash
composer install
pnpm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
pnpm run build
```

Run the full dev stack (serve + queue + vite):

```bash
composer dev
```

## Quality

```bash
composer phpstan   # static analysis (Larastan, level 5)
composer pint      # code style
./vendor/bin/pest  # tests
```

## Docker

Multi-stage build — `php-base`, `composer-deps`, `runtime` — serving nginx + php-fpm +
Horizon + scheduler through supervisord. Production assets (`public/build`) are committed
to the repo and copied into the image.

```bash
docker build -t jeffersongoncalves.dev.br .
```

### CI build & release

- A push to `main` that changes `composer.lock` triggers `auto-release-filament.yml`,
  which pushes a `release-X.Y.Z` tag.
- The `release-*.*.*` tag triggers `docker-build.yml`, which builds and pushes the image
  to GHCR (`ghcr.io/jeffersongoncalves/jeffersongoncalves.dev.br`) tagged with the release,
  the commit SHA, and `latest`, then creates the GitHub Release.

Production requires a reachable Redis instance and `QUEUE_CONNECTION=redis`.

## License

MIT

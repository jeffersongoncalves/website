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

## Production

Production assets (`public/build`) are committed to the repo — run `pnpm run build` and
commit after any change under `resources/`. Production uses PostgreSQL via env and requires
a reachable Redis instance with `QUEUE_CONNECTION=redis` (Horizon).

## License

MIT

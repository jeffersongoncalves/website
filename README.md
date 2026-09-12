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
composer phpstan   # static analysis (Larastan, level 5)
composer pint      # code style
./vendor/bin/pest  # tests
```

## Production

Production assets (`public/build`) are committed to the repo — run `bun run build` and
commit after any change under `resources/`. Production uses PostgreSQL via env and requires
a reachable Redis instance with `QUEUE_CONNECTION=redis` (Horizon).

### Server firewall (Forge / ***REMOVED***)

`ufw` allow list — SSH is **not** on port 22 (default-denied), it's on a custom port:

| Port | Access |
| --- | --- |
| ***REMOVED*** | SSH — allowed, except `***REMOVED***` (blocked: recurring brute-force subnet) |
| 80 / 443 | HTTP/HTTPS — open |
| 6379 (Redis) | localhost-only (`127.0.0.1:6379`), never firewall-exposed |

`ufw` evaluates rules top-down, first match wins — a subnet-specific `deny` must sit **above**
any general `allow` for the same port (`ufw insert 1 deny ...`), otherwise the broader allow
rule matches first and the deny is a no-op.

`fail2ban` runs the `sshd` jail (`bantime=600s`, `findtime=600s`, `maxretry=5`) as a second
layer behind the firewall block. A `recidive` jail (`/etc/fail2ban/jail.d/recidive.conf`)
escalates repeat offenders — 3 `sshd` bans within 24h triggers a 1-week, all-ports ban — which
auto-handles brute-force sources that rotate IPs within a subnet instead of banning the whole
CIDR by hand:

```ini
[recidive]
enabled = true
logpath = /var/log/fail2ban.log
banaction = %(banaction_allports)s
bantime = 1w
findtime = 1d
maxretry = 3
```

### Database backups (***REMOVED***)

Weekly local dumps via the OS-native `postgresql-common` timer — no cron, no custom script:

```bash
sudo systemctl enable --now pg_dump@18-main.timer
```

Dumps every database in the cluster to `/var/backups/postgresql/18-main/<timestamp>.dump/`
(one `.dump` file per database, plus `globals.sql` and cluster config), keeps the last 3
(`KEEP=3` in the unit). Trigger manually with
`sudo systemctl start pg_dump@18-main.service` or `sudo -u postgres pg_backupcluster 18-main dump`.

The template unit's identifier is `<version>-<cluster>` (`18-main`), **not** just the version
(`18`) — using the bare version fails with `AssertPathExists=/etc/postgresql/%I/postgresql.conf`
(assertion failure, not a real error) because the config actually lives at
`/etc/postgresql/18/main/postgresql.conf`.

This covers local-disk loss only — Forge's own automated backup-to-storage-provider feature is
locked behind the Business plan on this account. If the whole box goes down, these dumps go
with it; enabling that Forge feature (or a manual `pg_dump` → S3/rclone script) is the
remaining gap for real disaster recovery.

## License

MIT

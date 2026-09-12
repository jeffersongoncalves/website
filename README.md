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

### Offsite backup (Google Drive)

`spatie/laravel-backup` covers the disaster-recovery gap above: `.env` + a `pgsql` dump, zipped
and shipped to Google Drive daily. Scheduled in `routes/console.php` — `backup:run` at 01:00,
`backup:clean` at 01:30, `backup:monitor` at 05:00. Config in `config/backup.php`; the `google`
disk (`config/filesystems.php`) uses `masbug/flysystem-google-drive-ext`, which has no Laravel
service provider of its own — wired up manually in
`AppServiceProvider::registerGoogleDriveDisk()`.

Requires `GOOGLE_DRIVE_CLIENT_ID`/`_CLIENT_SECRET`/`_REFRESH_TOKEN` (OAuth "Web application"
client, not "Desktop app" — the Playground's redirect URI needs a configurable allow-list;
Desktop-type clients hardcode `http://localhost`). The refresh token only survives long-term
once the consent screen's publishing status is **"In production"** — tokens issued while it's
still "Testing" expire in 7 days regardless of being on the test-users list.

Two bugs hit setting this up, both fixed in this repo (not just worked around):

- **Empty `BACKUP_ARCHIVE_PASSWORD` silently enabled AES encryption.** `env()` returns `''` (not
  `null`) for a key present-but-blank in `.env`; `spatie/laravel-backup`'s config only treated a
  *missing* password as "no encryption", so the blank string still flipped `Zip` into encryption
  mode and libzip threw `ZipArchive::close(): Invalid argument` with no indication why — same
  error whether zipping 1 file or 20, reproducible in production but never in an isolated
  `ZipArchive` script (which doesn't touch that config path). Fixed upstream:
  [spatie/laravel-backup#1984](https://github.com/spatie/laravel-backup/pull/1984) — `config/backup.php`
  also coerces it defensively (`?: null`) so the app doesn't depend on the PR landing.
- **Google Drive `isReachable()` check fails on the very first run.** `masbug/flysystem-google-drive-ext`
  auto-creates the remote folder on *upload*, but `BackupDestination::isReachable()` lists that
  folder *before* any upload happens — a chicken-and-egg 404 the first time. One-off fix, not a
  recurring one: `Storage::disk('google')->makeDirectory(config('backup.backup.name'))` via
  `artisan tinker`, once.

## License

MIT

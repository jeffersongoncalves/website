#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    storage/app/public \
    storage/app/github/readme \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Two-pass ownership reset: the outer chown handles the freshly
# mkdir'd parents; the inner chown drills into the github cache root
# specifically because production runs on a volume that sometimes
# preserves root-owned subdirs across container restarts.
chown -R www-data:www-data storage bootstrap/cache
chown -R www-data:www-data storage/app/github
chmod -R 775 storage bootstrap/cache
chmod -R 775 storage/app/github

# Ensure sitemap files exist and are writable by www-data so the scheduler
# and the boot-time sitemap:generate can rewrite them without 403/permission errors.
touch public/sitemap.xml public/sitemap-pages.xml public/sitemap-projects.xml
chown www-data:www-data public/sitemap.xml public/sitemap-pages.xml public/sitemap-projects.xml
chmod 664 public/sitemap.xml public/sitemap-pages.xml public/sitemap-projects.xml

mkdir -p /var/log/supervisor

# Limpa caches antigos do build para evitar Horizon/worker herdar APP_ENV congelado.
# Nao recacheamos: cache de config/route/view costuma causar problemas em producao aqui.
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan event:clear || true

php artisan migrate --force || true
php artisan sitemap:generate || true

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf

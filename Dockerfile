# syntax=docker/dockerfile:1.7

# =============================================================================
# Stage 1: php-base — PHP + extensoes (compartilhado entre composer-deps e runtime)
# =============================================================================
FROM php:8.4-fpm-alpine AS php-base

# install-php-extensions e a ferramenta upstream do PHP Docker: instala extensoes
# com deps de sistema corretas, em paralelo, sem gerenciar .build-deps manual.
ADD --chmod=0755 \
    https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
    /usr/local/bin/

RUN install-php-extensions \
        opcache \
        bcmath \
        pcntl \
        intl \
        zip \
        gd \
        mbstring \
        curl \
        xml \
        pdo_pgsql \
        pdo_sqlite \
        redis \
        sodium

# =============================================================================
# Stage 2: composer-deps — resolve dependencias
# =============================================================================
FROM php-base AS composer-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

# BuildKit cache mount: ~/.composer persiste entre builds.
# Primeira build demora normal; subsequentes caem pra segundos se lock nao mudou.
RUN --mount=type=cache,target=/root/.composer,sharing=locked \
    composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

# =============================================================================
# Stage 3: runtime — imagem final
# =============================================================================
FROM php-base AS runtime

ARG IMAGE_SOURCE=""
LABEL org.opencontainers.image.source=${IMAGE_SOURCE}

# Runtime-only: sem build tools, sem PHPIZE_DEPS
RUN apk add --no-cache \
        nginx \
        supervisor \
        postgresql-client

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Source + vendor (vendor vem do stage anterior)
COPY . .
COPY --from=composer-deps /var/www/html/vendor ./vendor

# Autoload otimizado + package:discover (registra Horizon/Filament/etc)
RUN --mount=type=cache,target=/root/.composer,sharing=locked \
    composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && composer run-script post-autoload-dump

# Permissoes Laravel
RUN chown -R www-data:www-data storage bootstrap/cache database vendor \
    && chmod -R 775 storage bootstrap/cache database

# php:8.4-fpm-alpine nao escaneia /usr/local/etc/php/cli/conf.d/;
# FPM e CLI compartilham conf.d. Overrides CLI-only vao via flag -d no supervisord.
COPY docker/php/php-fpm.ini                  /usr/local/etc/php/conf.d/99-prod.ini
COPY docker/php/www-prod.conf                /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx/nginx-prod.conf            /etc/nginx/nginx.conf
COPY docker/nginx/default-prod.conf          /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord-prod.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint-prod.sh               /entrypoint.sh

RUN mkdir -p /etc/supervisor/conf.d /var/log/supervisor \
    && chmod +x /entrypoint.sh \
    && touch /var/log/php-error.log \
    && chown www-data:www-data /var/log/php-error.log \
    && chmod 664 /var/log/php-error.log

# =============================================================================
# SMOKE TESTS — falha o build imediatamente se alguma premissa essencial quebrar.
# Problemas do tipo "proc_open desabilitado" sao pegos aqui, nao em producao.
# =============================================================================
RUN set -eux; \
    echo "=== Smoke tests ==="; \
    php -r 'if (!function_exists("proc_open")) { fwrite(STDERR, "FAIL: proc_open disabled\n"); exit(1); }'; \
    php -r 'if (!function_exists("popen")) { fwrite(STDERR, "FAIL: popen disabled\n"); exit(1); }'; \
    php -r 'if (!function_exists("pcntl_fork")) { fwrite(STDERR, "FAIL: pcntl_fork missing\n"); exit(1); }'; \
    php -r 'if (!function_exists("posix_getpwuid")) { fwrite(STDERR, "FAIL: posix missing\n"); exit(1); }'; \
    php -r 'if (!function_exists("curl_exec")) { fwrite(STDERR, "FAIL: curl_exec disabled\n"); exit(1); }'; \
    php -r 'foreach (["redis","pdo_pgsql","pcntl"] as $e) if (!extension_loaded($e)) { fwrite(STDERR, "FAIL: ext $e missing\n"); exit(1); }'; \
    php -r 'if (!extension_loaded("Zend OPcache")) { fwrite(STDERR, "FAIL: Zend OPcache not loaded\n"); exit(1); }'; \
    php artisan --version; \
    php artisan list --raw | grep -q "^horizon " || { echo "FAIL: horizon commands not registered"; exit 1; }; \
    test -f config/horizon.php || { echo "FAIL: config/horizon.php missing"; exit 1; }; \
    echo "=== All smoke tests passed ==="

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]

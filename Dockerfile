# Debian (bookworm) base instead of Alpine (Mini PC migration, 2026-08-06): Alpine's
# Fastly-fronted package CDN (dl-cdn.alpinelinux.org) stalled indefinitely mid-
# download from this network on large packages (gcc, chromium) — established
# connection, zero bytes, forever — across every mitigation (IPv6 off, MTU 1400,
# alternate mirror, layer splitting, timeout/retry). Debian's apt runs on entirely
# separate mirror infrastructure (deb.debian.org) which the continuo image already
# builds cleanly against on this same network, so switching base sidesteps the stall
# rather than fighting it. Larger image, but fine on this 32GB / ~1TB dev box.
FROM php:8.2-fpm-bookworm AS base

# System deps in one layer: runtime tools + libraries the PHP extensions link
# against + the build toolchain ($PHPIZE_DEPS, defined by the official php image on
# Debian too) needed to compile them. Kept in the image rather than purged — this is
# a dev image on a machine with ample disk, and it matches continuo's simple apt
# pattern. chromium/chromium-driver drive symfony/panther's browser tests (they were
# only dropped on Alpine because of the CDN stall — restored here on reliable apt).
RUN apt-get update && apt-get install -y --no-install-recommends \
        ghostscript \
        qpdf \
        imagemagick \
        openssh-client \
        chromium \
        chromium-driver \
        libicu-dev \
        libzip-dev \
        libfreetype6-dev \
        libjpeg-dev \
        libpng-dev \
        libmagickwand-dev \
        zlib1g-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        intl \
        pdo_mysql \
        zip \
        opcache \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && pecl clear-cache \
    && rm -rf /var/lib/apt/lists/* /tmp/pear \
    # Allow ImageMagick to read PDFs (delegates to Ghostscript). Debian bookworm
    # ships ImageMagick 6, so the policy lives under ImageMagick-6/ (was -7 on
    # Alpine). Narrow sed on the rights+pattern avoids escaping the self-closing tag.
    && sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/' \
        /etc/ImageMagick-6/policy.xml || true

# Pre-create the SSH key target so Docker bind-mounts it as a file (not a directory)
RUN touch /run/hetzner_key

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# --- Development stage ---
FROM base AS dev

COPY docker/php/php.dev.ini $PHP_INI_DIR/conf.d/app.ini

# vendor/ is pre-copied from the Pi (Mini PC migration, 2026-08-06): rather than
# download dependencies over this network's flaky composer/GitHub path, vendor was
# copied from the Pi's running dev container (identical composer.lock; PHP packages
# are platform-independent) and is brought into the image by `COPY . .` below (see
# the .dockerignore note). `composer dump-autoload` then regenerates the autoloader
# locally — no network. Restore the composer-install step once the network is
# reliable again.
COPY . .
RUN composer dump-autoload

COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

# --- Production stage ---
FROM base AS prod

COPY docker/php/php.prod.ini $PHP_INI_DIR/conf.d/app.ini
COPY docker/php/zzz-clear-env.conf /usr/local/etc/php-fpm.d/zzz-clear-env.conf

# Install dependencies first (layer cache) — keep dev deps for the test runner
COPY composer.json composer.lock symfony.lock ./
# Same resilient wrapper as the dev stage — see note there.
RUN --mount=type=cache,target=/composer/cache \
    i=0; until timeout -k 10 900 env COMPOSER_HOME=/composer COMPOSER_MAX_PARALLEL_HTTP=1 \
        composer install --no-scripts --no-autoloader --prefer-dist --no-interaction; do \
        i=$((i+1)); [ "$i" -ge 12 ] && echo "composer failed after $i attempts" && exit 1; \
        echo "composer stalled/failed (flaky network), retry $i..."; sleep 3; \
    done

# Copy application
COPY . .

# Finalize autoloader and run post-install scripts (assets:install, importmap:install)
RUN composer dump-autoload --optimize \
    && APP_ENV=prod composer run-script post-install-cmd \
    && APP_ENV=prod php bin/console asset-map:compile \
    && mkdir -p public/images/styles public/liederlisten \
    && chown -R www-data:www-data var/ public/images/styles public/liederlisten \
    && cp -r public/assets /var/assets-seed

COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/entrypoint.sh"]

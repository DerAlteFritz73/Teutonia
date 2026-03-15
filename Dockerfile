FROM php:8.2-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    ghostscript \
    imagemagick \
    imagemagick-dev \
    icu-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        intl \
        pdo_mysql \
        zip \
        opcache \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear /var/cache/apk/* \
    # Allow ImageMagick to read PDFs (delegates to Ghostscript)
    && sed -i 's/<policy domain="coder" rights="none" pattern="PDF" \/>/<policy domain="coder" rights="read|write" pattern="PDF" \/>/' \
        /etc/ImageMagick-7/policy.xml || true

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# --- Development stage ---
FROM base AS dev

COPY docker/php/php.dev.ini $PHP_INI_DIR/conf.d/app.ini

CMD ["php-fpm"]

# --- Production stage ---
FROM base AS prod

COPY docker/php/php.prod.ini $PHP_INI_DIR/conf.d/app.ini

# Install dependencies first (layer cache)
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Copy application
COPY . .

# Finalize autoloader and run post-install scripts (assets:install, importmap:install)
RUN composer dump-autoload --optimize --no-dev \
    && APP_ENV=prod composer run-script post-install-cmd --no-dev \
    && chown -R www-data:www-data var/

EXPOSE 9000
CMD ["php-fpm"]

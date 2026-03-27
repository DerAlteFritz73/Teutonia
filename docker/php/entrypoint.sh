#!/bin/sh
# Seed the assets volume with files built into the image
if [ -d /var/assets-seed ]; then
    cp -r /var/assets-seed/. /var/assets/
fi

# Ensure upload directories are writable by www-data (volumes start owned by root)
chown -R www-data:www-data \
    /var/www/html/public/images/posts \
    /var/www/html/public/images/styles \
    /var/www/html/public/pdfs \
    2>/dev/null || true

exec php-fpm

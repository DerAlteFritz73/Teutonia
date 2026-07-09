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
    /var/www/html/public/liederlisten \
    2>/dev/null || true

# Make the Hetzner SSH key readable by www-data (bind-mount is owned by root)
if [ -f /run/hetzner_key ]; then
    mkdir -p /tmp/hetzner_ssh
    cp /run/hetzner_key /tmp/hetzner_ssh/id_ed25519
    chmod 600 /tmp/hetzner_ssh/id_ed25519
    chown www-data:www-data /tmp/hetzner_ssh/id_ed25519
fi

exec php-fpm

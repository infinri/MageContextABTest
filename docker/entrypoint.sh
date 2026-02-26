#!/bin/bash
set -e

MAGE_ROOT=/var/www/html

# -------------------------------------------------------
# Create required Magento directories
# -------------------------------------------------------
for dir in var generated pub/static pub/media app/etc; do
    mkdir -p "$MAGE_ROOT/$dir"
done

# -------------------------------------------------------
# Permissions
# -------------------------------------------------------
chown -R www-data:www-data \
    "$MAGE_ROOT/var" \
    "$MAGE_ROOT/generated" \
    "$MAGE_ROOT/pub/static" \
    "$MAGE_ROOT/pub/media" \
    "$MAGE_ROOT/app/etc"

# -------------------------------------------------------
# Composer install (if vendor is missing)
# -------------------------------------------------------
if [ -f "$MAGE_ROOT/composer.json" ] && [ ! -d "$MAGE_ROOT/vendor/magento" ]; then
    echo "[entrypoint] vendor/magento not found - running composer install..."
    cd "$MAGE_ROOT" && composer install --no-interaction --prefer-dist
    chown -R www-data:www-data "$MAGE_ROOT/vendor"
fi

# -------------------------------------------------------
# Create log directory for cron
# -------------------------------------------------------
mkdir -p "$MAGE_ROOT/var/log"
chown -R www-data:www-data "$MAGE_ROOT/var/log"

echo "[entrypoint] Starting services via supervisor..."
exec "$@"

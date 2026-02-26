#!/bin/bash
set -e

MAGE_ROOT=/var/www/html

# -------------------------------------------------------
# Global umask: new files are group-writable (664/775)
# -------------------------------------------------------
umask 002

# -------------------------------------------------------
# Create required Magento directories
# -------------------------------------------------------
WRITABLE_DIRS="var generated pub/static pub/media app/etc"
for dir in $WRITABLE_DIRS; do
    mkdir -p "$MAGE_ROOT/$dir"
done

# -------------------------------------------------------
# Permissions — setgid so BOTH root and www-data can write
#   1. Group  → www-data on all writable dirs
#   2. g+rwsX → group read/write + setgid on dirs (new
#               files/dirs inherit www-data group)
# -------------------------------------------------------
for dir in $WRITABLE_DIRS; do
    chgrp -R www-data "$MAGE_ROOT/$dir"
    chmod -R g+rwX    "$MAGE_ROOT/$dir"
    find "$MAGE_ROOT/$dir" -type d -exec chmod g+s {} +
done

# -------------------------------------------------------
# Composer install (if vendor is missing)
# -------------------------------------------------------
if [ -f "$MAGE_ROOT/composer.json" ] && [ ! -d "$MAGE_ROOT/vendor/magento" ]; then
    echo "[entrypoint] vendor/magento not found - running composer install..."
    cd "$MAGE_ROOT" && composer install --no-interaction --prefer-dist
    chgrp -R www-data "$MAGE_ROOT/vendor"
    chmod -R g+rX     "$MAGE_ROOT/vendor"
fi

echo "[entrypoint] Starting services via supervisor..."
exec "$@"

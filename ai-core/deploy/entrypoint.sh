#!/bin/sh
set -eu

# Keep uploaded product photos in the persistent public-storage volume.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

# Do not run migrations or seeders automatically at container startup.
# A release must review/backup the target database before applying migrations.
# Opt-in escape hatch for a supervised first boot: set RUN_MIGRATIONS=1,
# then remove the setting afterwards so later restarts stay read-only.
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrations:reconcile-existing --no-interaction
    php artisan migrate --force --no-interaction
fi

if [ ! -L public/storage ] && [ ! -e public/storage ]; then
    php artisan storage:link
fi

exec "$@"

#!/bin/sh
set -eu

# Keep uploaded product photos in the persistent public-storage volume.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

# Do not run migrations or seeders automatically at container startup.
# A release must review/backup the target database before applying migrations.
if [ ! -L public/storage ] && [ ! -e public/storage ]; then
    php artisan storage:link
fi

exec "$@"

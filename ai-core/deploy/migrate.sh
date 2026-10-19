#!/usr/bin/env bash
set -euo pipefail

echo ">>> Running database migrations..."
php artisan migrate --force

# Intentionally do not seed automatically here. DatabaseSeeder creates demo
# users, restaurants, menus, and orders; reseeding an existing environment can
# pollute live business data. Seed only through a separately reviewed,
# explicitly approved command against a known-empty database.

echo ">>> Caching configuration..."
php artisan config:cache

echo ">>> Caching routes..."
php artisan route:cache

echo ">>> Caching views..."
php artisan view:cache

echo ">>> Linking storage..."
php artisan storage:link

echo ">>> Optimizing application..."
php artisan optimize

echo ">>> Deployment migration completed successfully."

#!/bin/sh

echo "Bringing Laravel out of maintenance mode..."
php artisan up || true

echo "Clearing Laravel caches..."
php artisan optimize:clear || true
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan cache:clear || true

echo "Running database migrations..."
php artisan migrate --force

echo "Creating storage link..."
php artisan storage:link || true

echo "Caching Laravel config..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Railway init script completed."
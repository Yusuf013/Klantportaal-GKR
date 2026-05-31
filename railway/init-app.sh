#!/bin/sh

echo "Clearing Laravel caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "Running database migrations..."
php artisan migrate --force

echo "Caching Laravel config..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Railway init script completed."
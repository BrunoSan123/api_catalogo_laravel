#!/bin/sh
set -e

cd /var/www/html

# Install composer dependencies if vendor dir missing
if [ ! -d "vendor" ]; then
  if [ -f composer.lock ] || [ -f composer.json ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
  fi
fi

# Set permissions
chown -R www-data:www-data storage bootstrap/cache || true

# Run Elasticsearch setup (idempotent) if artisan exists
if [ -f artisan ]; then
  # run but don't fail startup if it errors
  php artisan es:setup || true
fi

exec "$@"

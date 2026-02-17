#!/bin/sh
set -e

cd /var/www/html

# Install composer dependencies if vendor dir missing
if [ ! -d "vendor" ]; then
  if [ -f composer.lock ] || [ -f composer.json ]; then
    echo "vendor not found — attempting composer install (errors won't stop startup)"
    set +e
    composer install --no-interaction --prefer-dist --optimize-autoloader
    _composer_rc=$?
    set -e
    if [ "${_composer_rc}" -ne 0 ]; then
      echo "composer install exited with code ${_composer_rc}, continuing container startup"
    fi
  fi
fi

# Set permissions
chown -R www-data:www-data storage bootstrap/cache || true

# Run Elasticsearch setup (idempotent) if artisan exists
if [ -f artisan ]; then
  # run but don't fail startup if it errors
  php artisan es:setup || true
fi

if [ "$#" -gt 0 ]; then
  exec "$@"
else
  echo "No command provided to entrypoint — starting php-fpm"
  exec php-fpm
fi

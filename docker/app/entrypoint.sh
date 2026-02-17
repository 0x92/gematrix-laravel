#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

if [ ! -d vendor ]; then
  composer install --no-interaction --prefer-dist
fi

if [ -f .env ]; then
  if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    php artisan key:generate --force
  fi
fi

if [ "$DB_CONNECTION" = "pgsql" ]; then
  until pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" >/dev/null 2>&1; do
    echo "Waiting for PostgreSQL..."
    sleep 2
  done

  php artisan migrate --force --seed
fi

exec "$@"

#!/bin/sh
# Starts the API and the back office for local development:
# dependencies, app key, migrations (as lms_owner), demo data, then the web
# server and the scheduler (the per-minute attendance sweeper).
set -e
cd /app

[ -f .env ] || cp .env.example .env
[ -d vendor ] || composer install --no-interaction --prefer-dist
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force

echo "Waiting for Postgres..."
until php -r 'exit(@fsockopen(getenv("DB_HOST"), (int) (getenv("DB_PORT") ?: 5432)) ? 0 : 1);'; do sleep 1; done

php artisan migrate --database=pgsql_owner --force
php artisan db:seed --force
php artisan filament:assets >/dev/null
[ -e public/storage ] || php artisan storage:link >/dev/null

php artisan schedule:work >/tmp/schedule.log 2>&1 &

echo ""
echo "  Back office: http://demo.localhost:8000/admin  (or http://localhost:8000/admin)"
echo "  Sign in:     owner@demo.test / password"
echo ""
exec php artisan serve --host=0.0.0.0 --port=8000

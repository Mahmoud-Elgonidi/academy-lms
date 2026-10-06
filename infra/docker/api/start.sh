#!/bin/sh
# Starts the API and the back office for local development:
# dependencies, app key, migrations (as lms_owner), demo data, then the web
# server and the scheduler (attendance sweeper every minute, credit expiry
# hourly, new sessions from weekly times nightly).
#
# After a `git pull`, `docker compose up` is enough: new PHP packages are
# installed when composer.lock changed, new migrations run, and the demo
# data is rebuilt when its version below changes.
set -e
cd /app

# Bump when the demo data changes shape, so existing setups rebuild it.
DEMO_VERSION=4

[ -f .env ] || cp .env.example .env

# The code is mounted from the host, so vendor/ survives between runs:
# install again whenever composer.lock is not the one vendor/ was built from.
if [ ! -f vendor/autoload.php ] || ! cmp -s composer.lock vendor/.installed-composer.lock; then
    echo "Installing PHP packages (composer.lock changed)..."
    composer install --no-interaction --prefer-dist
    cp composer.lock vendor/.installed-composer.lock
fi
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
php artisan optimize:clear >/dev/null

echo "Waiting for Postgres..."
until php -r 'exit(@fsockopen(getenv("DB_HOST"), (int) (getenv("DB_PORT") ?: 5432)) ? 0 : 1);'; do sleep 1; done

if [ "$(cat storage/app/demo-version 2>/dev/null)" != "$DEMO_VERSION" ]; then
    echo "Demo data is new or out of date: rebuilding the local database..."
    php artisan migrate:fresh --database=pgsql_owner --force
    php artisan db:seed --force
    echo "$DEMO_VERSION" > storage/app/demo-version
else
    php artisan migrate --database=pgsql_owner --force
    php artisan db:seed --force
fi
php artisan filament:assets >/dev/null
[ -e public/storage ] || php artisan storage:link >/dev/null

php artisan schedule:work >/tmp/schedule.log 2>&1 &

echo ""
echo "  Back office: http://demo.localhost:8000/admin  (or http://localhost:8000/admin)"
echo "  Sign in:     owner@demo.test / password   (also scheduler@, accountant@demo.test)"
echo "  Fresh demo:  docker compose exec api composer demo"
echo ""
exec php artisan serve --host=0.0.0.0 --port=8000

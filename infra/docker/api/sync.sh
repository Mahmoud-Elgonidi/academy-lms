#!/bin/sh
# Brings the running app in step with the code on disk: PHP packages when
# composer.lock changed, the class map when composer.json changed, the app key, new migrations (as lms_owner), and the
# demo data, rebuilt when its version (apps/api/database/demo-version)
# changed. Safe to run again and again: each step does nothing when there is
# nothing to do. start.sh runs it at start and whenever the code changes.
set -e
cd /app

[ -f .env ] || cp .env.example .env

# The code is mounted from the host, so vendor/ survives between runs:
# install again whenever composer.lock is not the one vendor/ was built from,
# and rebuild the class map when composer.json changed (a new module).
if [ ! -f vendor/autoload.php ] || ! cmp -s composer.lock vendor/.installed-composer.lock; then
    echo "Installing PHP packages (composer.lock changed)..."
    composer install --no-interaction --prefer-dist
    cp composer.lock vendor/.installed-composer.lock
    cp composer.json vendor/.installed-composer.json
elif ! cmp -s composer.json vendor/.installed-composer.json; then
    echo "Updating the class map (composer.json changed)..."
    composer dump-autoload --no-interaction
    cp composer.json vendor/.installed-composer.json
fi
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
php artisan optimize:clear >/dev/null

until php -r 'exit(@fsockopen(getenv("DB_HOST"), (int) (getenv("DB_PORT") ?: 5432)) ? 0 : 1);'; do
    echo "Waiting for Postgres..."
    sleep 1
done

wanted="$(cat database/demo-version 2>/dev/null || echo 1)"
if [ "$(cat storage/app/demo-version 2>/dev/null)" != "$wanted" ]; then
    echo "Demo data is new or out of date: rebuilding the local database..."
    php artisan migrate:fresh --database=pgsql_owner --force
    php artisan db:seed --force
    echo "$wanted" > storage/app/demo-version
else
    php artisan migrate --database=pgsql_owner --force
fi
php artisan filament:assets >/dev/null
[ -e public/storage ] || php artisan storage:link >/dev/null

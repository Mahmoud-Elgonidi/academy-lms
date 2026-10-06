#!/bin/sh
# Starts the API, the back office and the teacher area for local
# development: everything sync.sh does (packages, app key, migrations,
# demo data), then the scheduler (attendance every minute, reports and
# homework every five, credit expiry hourly, sessions from weekly times
# nightly) and the web server.
#
# A `git pull` while this runs needs nothing else: every few seconds the
# container checks composer.json and .lock, the migration files and the demo version,
# and runs sync.sh again when one of them changed. PHP code itself is read
# fresh on every request.
set -e
cd /app

sh /docker/sync.sh

# What sync.sh depends on; a change means "sync again".
fingerprint() {
    {
        cat composer.json composer.lock database/demo-version 2>/dev/null
        ls database/migrations modules/*/database/migrations 2>/dev/null
    } | md5sum
}

(
    synced="$(fingerprint)"
    while sleep 5; do
        current="$(fingerprint)"
        [ "$current" = "$synced" ] && continue
        sleep 2   # let a git pull finish writing
        echo ""
        echo "The code changed: updating packages, migrations and demo data..."
        if sh /docker/sync.sh; then
            synced="$(fingerprint)"
            echo "Up to date."
        else
            echo "The update failed; it will be tried again in a minute (or run: docker compose restart api)."
            sleep 60
        fi
    done
) &

php artisan schedule:work >/tmp/schedule.log 2>&1 &

echo ""
echo "  Back office:  http://demo.localhost:8000/admin  (or http://localhost:8000/admin)"
echo "  Sign in:      owner@demo.test / password   (also scheduler@, accountant@demo.test)"
echo "  Teacher area: http://localhost:8000/teach    khaled@demo.test / password"
echo "  Fresh demo:   docker compose exec api composer demo"
echo ""
exec php artisan serve --host=0.0.0.0 --port=8000

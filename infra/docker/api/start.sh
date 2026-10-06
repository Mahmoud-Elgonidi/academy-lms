#!/bin/sh
# Starts the API, the back office and the teacher area for local
# development: everything sync.sh does (packages, app key, migrations,
# demo data), then Reverb and the live board relay, the scheduler (attendance, reminders and held messages
# every minute, reports and homework every five, credit expiry hourly,
# sessions from weekly times nightly), the queue worker and the web server.
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

# Live screens: Reverb holds the browsers' connections, and scheduling:live
# passes changes from the database to it (one signal per change, nothing while
# idle). Both stop by themselves after an update and start again here.
( while true; do php artisan reverb:start --host=0.0.0.0 --port=8080 >>/tmp/reverb.log 2>&1; sleep 1; done ) &
( while true; do php artisan scheduling:live >>/tmp/live.log 2>&1; sleep 1; done ) &

# Notifications go out from the queue. sync.sh restarts the worker after an
# update (queue:restart), and this loop starts it again on the new code.
( while true; do php artisan queue:work --queue=notifications,default --tries=3 --max-time=3600 >>/tmp/queue.log 2>&1; sleep 1; done ) &

echo ""
echo "  Back office:  http://demo.localhost:8000/admin  (or http://localhost:8000/admin)"
echo "  Sign in:      owner@demo.test / password   (also scheduler@, accountant@demo.test)"
echo "  Teacher area: http://localhost:8000/teach    khaled@demo.test / password"
echo "  Emails:       http://localhost:8025 (Mailpit); WhatsApp and push are in storage/logs/laravel.log"
echo "  Fresh demo:   docker compose exec api composer demo"
echo ""
exec php artisan serve --host=0.0.0.0 --port=8000

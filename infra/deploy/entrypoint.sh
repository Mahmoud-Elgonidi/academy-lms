#!/bin/sh
# What one container of the production stack runs (infra/deploy/compose.yml):
#
#   web        FrankenPHP: the panels, the API, under /app/ Reverb's browser connections,
#              and the portal's pages (/ar, /en, /_next, /bff) passed on to the portal
#   portal     the teachers' portal (apps/web), Next's own server on 3000
#   reverb     Reverb, the browsers' live connections
#   worker     the queue (notifications first), and beside it the games designer's (rounds take minutes)
#   scheduler  the scheduler (attendance, reminders, sweeps, the night's sessions)
#   relay      live:relay, database changes to Reverb
#   migrate    migrations, as lms_owner, then fresh table statistics (db:analyze)
#   seed-demo  the demo academies (does nothing once "demo" exists)
#
# Anything else runs as given, e.g. `academy php artisan platform:code admin@platform.test`.
set -e
cd /app

# The portal is Node alone: none of Laravel's start-up.
if [ "${1:-web}" = portal ]; then
    cd /portal
    export PORT=3000 HOSTNAME=0.0.0.0 NODE_ENV=production NEXT_TELEMETRY_DISABLED=1
    exec node server.js
fi

# The settings come from this container's environment (compose's env_file):
# cache them, the routes, the events and the views once, at start.
php artisan optimize >/dev/null

case "${1:-web}" in
    web) export WEB_PHP_THREADS="${WEB_PHP_THREADS:-8}"; exec frankenphp run --config /etc/academy/Caddyfile --adapter caddyfile ;;
    reverb) exec php artisan reverb:start --host=0.0.0.0 --port=8080 ;;
    worker)
        # QUEUE_WORKERS workers (default 2: one slow outside call, WhatsApp, SMS, push or mail,
        # does not hold up the lesson reminders behind it), the games designer's and the AI
        # helpers' (assist: calls of a minute or two that never wait behind messages). queue:work
        # ends itself every hour, after a job that ran too long or past its memory: each is
        # started again on its own, so that never stops the container and the others with it.
        # A stop is passed on, and each worker finishes the job in hand.
        keep() {
            trap 'stop=1; kill -TERM "$child" 2>/dev/null || true' TERM INT
            stop=0
            while [ "$stop" = 0 ]; do
                php artisan queue:work "$@" & child=$!
                wait "$child" || true
                if [ "$stop" = 1 ]; then wait "$child" || true; fi
                [ "$stop" = 1 ] || sleep 1
            done
        }
        keep designer --queue=designer --tries=1 --timeout=1000 --max-time=3600 & pids=$!
        keep assist --queue=assist --tries=3 --timeout=240 --max-time=3600 & pids="$pids $!"
        n="${QUEUE_WORKERS:-2}"
        while [ "$n" -gt 0 ]; do
            keep --queue=notifications,default --tries=3 --max-time=3600 & pids="$pids $!"
            n=$((n - 1))
        done
        trap 'kill -TERM $pids 2>/dev/null || true' TERM INT
        wait || true
        wait
        exit 0 ;;
    scheduler) exec php artisan schedule:work ;;
    relay) exec php artisan live:relay ;;
    migrate) php artisan migrate --database=pgsql_owner --force && exec php artisan db:analyze ;;
    seed-demo) exec php artisan db:seed --force ;;
    *) exec "$@" ;;
esac

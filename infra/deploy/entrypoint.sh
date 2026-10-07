#!/bin/sh
# What one container of the production stack runs (infra/deploy/compose.yml):
#
#   web        FrankenPHP: the panels, the API, under /app/ Reverb's browser connections,
#              and the portal's pages (/ar, /en, /_next, /bff) passed on to the portal
#   portal     the teachers' portal (apps/web), Next's own server on 3000
#   reverb     Reverb, the browsers' live connections
#   worker     the queue (notifications first)
#   scheduler  the scheduler (attendance, reminders, sweeps, the night's sessions)
#   relay      live:relay, database changes to Reverb
#   migrate    migrations, as lms_owner
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
    web) exec frankenphp run --config /etc/academy/Caddyfile --adapter caddyfile ;;
    reverb) exec php artisan reverb:start --host=0.0.0.0 --port=8080 ;;
    worker) exec php artisan queue:work --queue=notifications,default --tries=3 --max-time=3600 ;;
    scheduler) exec php artisan schedule:work ;;
    relay) exec php artisan live:relay ;;
    migrate) exec php artisan migrate --database=pgsql_owner --force ;;
    seed-demo) exec php artisan db:seed --force ;;
    *) exec "$@" ;;
esac

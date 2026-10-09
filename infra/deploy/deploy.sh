#!/usr/bin/env bash
# Deploys or updates the LMS on this server, apart from everything else on it.
# GitHub Actions copies this folder to ~/academy-lms and runs it
# (.github/workflows/deploy.yml). By hand, from this folder:
#
#   DOMAIN=progma.store ./deploy.sh ghcr.io/<owner>/academy-lms:<tag>
#
# What it touches: this folder, the Docker project "academy-lms" (its own
# containers, network and volumes) and one port on 127.0.0.1. Nothing else:
# no packages, no system settings, no other containers or images, and no web
# server settings (setup-nginx.sh does that, once, when you run it).
#
#   DOMAIN      the platform's domain; academies are <slug>.DOMAIN (required)
#   DEMO_DATA   1: load the demo academies if they are not there yet
#   A registry login may come on stdin: the user on the first line, the token on the second.
set -euo pipefail

IMAGE="${1:?usage: deploy.sh <image>}"
DOMAIN="${DOMAIN:?set DOMAIN, e.g. DOMAIN=progma.store}"
DEMO_DATA="${DEMO_DATA:-0}"
HERE="$(cd "$(dirname "$0")" && pwd)"
PROJECT=academy-lms
cd "$HERE"
umask 077

say() { printf '\n==> %s\n' "$*"; }
fail() { printf '\n!! %s\n' "$*" >&2; exit 1; }

# The registry login stays in this folder, not in the user's ~/.docker.
export DOCKER_CONFIG="$HERE/.docker"
mkdir -p "$DOCKER_CONFIG"

# Docker as this user, or through sudo without a password. Never installed or reconfigured here.
if docker info >/dev/null 2>&1; then
    dk() { docker "$@"; }
elif sudo -n docker info >/dev/null 2>&1; then
    dk() { sudo -n env DOCKER_CONFIG="$DOCKER_CONFIG" docker "$@"; }
else
    fail "This user cannot run Docker (not installed, or not in the docker group). Nothing was changed. See README.md, \"The server\"."
fi
dk compose version >/dev/null 2>&1 || fail "Docker Compose v2 (docker compose) is missing. Nothing was changed."
compose() { dk compose -p "$PROJECT" -f "$HERE/compose.yml" "$@"; }

for f in compose.yml roles.sql postgres-init.sh postgres-start.sh pg_hba.conf backup.sh offsite.sh; do
    [ -f "$f" ] || fail "$f is missing next to deploy.sh."
done

# The TCP ports something listens on (any address), from ss or straight from the kernel.
listening_ports() {
    if command -v ss >/dev/null 2>&1; then
        ss -ltnH | awk '{ n = split($4, a, ":"); print a[n] }'
    else
        # /proc/net/tcp and tcp6: the local address is IP:PORT in hex; state 0A is "listening".
        for f in /proc/net/tcp /proc/net/tcp6; do
            if [ -r "$f" ]; then awk 'NR > 1 && $4 == "0A" { split($2, a, ":"); print a[2] }' "$f"; fi
        done | while read -r hex; do printf '%d\n' "0x$hex"; done
    fi
}
port_taken() { local open; open="$(listening_ports || true)"; grep -qx "$1" <<<"$open"; }
secret() { head -c 30 /dev/urandom | base64 | tr '+/' 'Kq' | tr -d '\n='; }
setting() { grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- || true; }
set_setting() {
    if grep -qE "^$1=" .env; then
        sed -i "s#^$1=.*#$1=$2#" .env
    else
        printf '%s=%s\n' "$1" "$2" >> .env
    fi
}

# ---------------------------------------------------------------------------
# First deploy: this server's settings and secrets, written once and kept.
if [ ! -f .env ]; then
    say "First deploy: writing this server's settings (.env, postgres.env, readable by $(id -un) only)"
    port=""
    for p in $(seq 18080 18099); do
        if ! port_taken "$p"; then port="$p"; break; fi
    done
    [ -n "$port" ] || fail "No free port between 18080 and 18099 on 127.0.0.1. Nothing was changed."

    printf 'POSTGRES_PASSWORD=%s\n' "$(secret)" > postgres.env
    demo_gateway=false
    [ "$DEMO_DATA" = 1 ] && demo_gateway=true
    cat > .env <<EOF
# This server's settings for the LMS (infra/deploy/README.md). Written by
# deploy.sh on the first deploy and never overwritten; keep it private.
IMAGE=$IMAGE
HTTP_PORT=$port

APP_NAME="Academy LMS"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')
APP_URL=https://$DOMAIN
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
LOG_CHANNEL=stderr
LOG_LEVEL=info

# The academies: <slug>.$DOMAIN (or one on $DOMAIN itself: PLATFORM_HOME_ACADEMY=<slug>).
# The platform console: https://$DOMAIN/console
PLATFORM_DOMAIN=$DOMAIN
PLATFORM_HOME_ACADEMY=
PLATFORM_CONSOLE_DOMAIN=
PLATFORM_CONSOLE_URL=

# The server's nginx and the container's web server, on Docker's private networks.
TRUSTED_PROXIES=127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=lms
DB_USERNAME=lms_app
DB_PASSWORD=$(secret)
DB_OWNER_USERNAME=lms_owner
DB_OWNER_PASSWORD=$(secret)
DB_PLATFORM_USERNAME=lms_platform
DB_PLATFORM_PASSWORD=$(secret)

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
CACHE_STORE=redis
QUEUE_CONNECTION=redis
FILESYSTEM_DISK=local

# Live screens: the app talks to Reverb inside the stack; browsers reach it on
# their academy's own host, under /app/ (the web container passes it on).
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=academy-lms
REVERB_APP_KEY=$(secret)
REVERB_APP_SECRET=$(secret)
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_PUBLIC_PORT=443
REVERB_PUBLIC_SCHEME=https

# Email (alerts to the platform team, welcome links, codes by email): in the log until an
# SMTP account is set here (the domain's own mail, Amazon SES, Postmark, Brevo, ...), then
# MAIL_MAILER=smtp, docker compose up -d, and the console's "Send a test email" (RUNBOOKS.md, "Email").
# Port 587 is STARTTLS; for 465 add MAIL_SCHEME=smtps.
MAIL_MAILER=log
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=no-reply@$DOMAIN
MAIL_FROM_NAME="Academy LMS"

# Lessons by an outside link until LiveKit is set up for this server.
CLASSROOM_PROVIDER=external
# Recording lessons (when an academy turns it on) needs LiveKit Egress too: it
# writes into the same S3 bucket (RECORDINGS_DISK=s3), or into a volume the app
# also reads (RECORDINGS_RECORDER_ROOT: the app's recordings folder as Egress sees it).
LIVEKIT_EGRESS_URL=
RECORDINGS_DISK=local
RECORDINGS_RECORDER_ROOT=

# Signing in with Google and Apple: off until the platform's own clients are
# registered with each (their callback is https://$DOMAIN/auth/social/{google|apple}/callback).
SOCIAL_REDIRECT_BASE=https://$DOMAIN
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_APP_CLIENT_IDS=
APPLE_SERVICES_ID=
APPLE_APP_IDS=

# The demo data's sign-in on this server (when it is loaded): every demo
# account's password, and the console's authenticator secret.
DEMO_PASSWORD=$(secret | cut -c1-16)
DEMO_PLATFORM_2FA_SECRET=$(head -c 20 /dev/urandom | base32 | tr -d '=\n')
BILLING_DEMO_GATEWAY=$demo_gateway

# Health (RUNBOOKS.md): GET /health answers an uptime monitor that sends this
# token; the console's "System health" page and the alerts go to the platform
# team (and to HEALTH_ALERT_TO, comma-separated) once mail is set up.
HEALTH_TOKEN=$(secret)
HEALTH_ALERT_TO=
# Backups: the backup part (compose.yml) every day at BACKUP_AT UTC, kept BACKUP_KEEP_DAYS days.
BACKUPS_EXPECTED=true
BACKUP_AT=05:30
BACKUP_KEEP_DAYS=7
# The off-site copy (the offsite part; RUNBOOKS.md, "The off-site copy"): off until a bucket
# (any S3-compatible storage) and an age public key are set. Only the public key is here:
# the private one, which reads the copies, stays with the platform team, off this server.
OFFSITE_S3_ENDPOINT=
OFFSITE_S3_REGION=
OFFSITE_S3_BUCKET=
OFFSITE_S3_PREFIX=academy-lms
OFFSITE_S3_ACCESS_KEY=
OFFSITE_S3_SECRET_KEY=
OFFSITE_AGE_RECIPIENT=
OFFSITE_KEEP_DAYS=30
EOF
    chmod 600 .env postgres.env
fi

# Settings added since this server's .env was written: added once, with their defaults.
add_if_missing() { grep -qE "^$1=" .env || printf '%s=%s\n' "$1" "$2" >> .env; }
add_if_missing HEALTH_TOKEN "$(secret)"
add_if_missing HEALTH_ALERT_TO ""
add_if_missing BACKUPS_EXPECTED true
for k in MAIL_HOST MAIL_USERNAME MAIL_PASSWORD OFFSITE_S3_ENDPOINT OFFSITE_S3_REGION OFFSITE_S3_BUCKET \
    OFFSITE_S3_ACCESS_KEY OFFSITE_S3_SECRET_KEY OFFSITE_AGE_RECIPIENT; do
    add_if_missing "$k" ""
done
add_if_missing MAIL_PORT 587
add_if_missing OFFSITE_S3_PREFIX academy-lms
add_if_missing OFFSITE_KEEP_DAYS 30
chmod 700 "$HERE" "$DOCKER_CONFIG"

HTTP_PORT="$(setting HTTP_PORT)"
[ -n "$HTTP_PORT" ] || fail "HTTP_PORT is missing from .env."
PREVIOUS="$(setting IMAGE)"

# The port is ours, or free; anything else holding it is left alone.
running_web="$(compose ps -q --status running web 2>/dev/null || true)"
if [ -z "$running_web" ] && port_taken "$HTTP_PORT"; then
    fail "Port $HTTP_PORT on this server is used by something else. Pick a free one for HTTP_PORT in .env. Nothing was changed."
fi

# ---------------------------------------------------------------------------
if [ ! -t 0 ]; then
    reg_user=""; reg_token=""
    read -r reg_user || true
    read -r reg_token || true
    if [ -n "$reg_token" ]; then
        say "Signing in to ${IMAGE%%/*} (for this folder only)"
        printf '%s' "$reg_token" | dk login "${IMAGE%%/*}" -u "$reg_user" --password-stdin >/dev/null
    fi
fi

say "Getting $IMAGE"
dk image inspect "$IMAGE" >/dev/null 2>&1 || dk pull "$IMAGE"
set_setting IMAGE "$IMAGE"

say "Database and cache"
compose up -d postgres redis
for _ in $(seq 1 60); do
    [ "$(dk inspect -f '{{.State.Health.Status}}' "$(compose ps -q postgres)" 2>/dev/null)" = healthy ] && break
    sleep 2
done
[ "$(dk inspect -f '{{.State.Health.Status}}' "$(compose ps -q postgres)")" = healthy ] \
    || { compose logs --tail=60 postgres; fail "Postgres did not become ready."; }

say "Migrations (as lms_owner)"
compose run --rm --no-deps -T web migrate

if [ "$DEMO_DATA" = 1 ]; then
    say "Demo academies (only if they are not there yet)"
    compose run --rm --no-deps -T web seed-demo
fi

say "Starting the new version"
compose up -d --remove-orphans

healthy=0
for _ in $(seq 1 45); do
    if dk exec "$(compose ps -q web)" php -r "exit(@file_get_contents('http://127.0.0.1:8080/up') === false ? 1 : 0);" 2>/dev/null; then
        healthy=1; break
    fi
    sleep 2
done
if [ "$healthy" != 1 ]; then
    compose logs --tail=80 web
    if [ -n "$PREVIOUS" ] && [ "$PREVIOUS" != "$IMAGE" ]; then
        say "The new version did not answer; going back to $PREVIOUS (migrations stay applied)"
        set_setting IMAGE "$PREVIOUS"
        compose up -d --remove-orphans
    fi
    fail "The web container did not answer on /up."
fi

# Our own older images only: this one and the one before stay, for a quick way back.
repo="${IMAGE%:*}"
dk image ls "$repo" --format '{{.Repository}}:{{.Tag}}' | while read -r ref; do
    [ "$ref" = "$IMAGE" ] || [ "$ref" = "$PREVIOUS" ] || dk image rm "$ref" >/dev/null 2>&1 || true
done
dk logout "${IMAGE%%/*}" >/dev/null 2>&1 || true

# Through the published port, from the server itself (the check above ran inside the container).
if command -v curl >/dev/null 2>&1; then
    curl -fsS -o /dev/null --max-time 10 "http://127.0.0.1:$HTTP_PORT/up" \
        || fail "The app answers inside its container but not on 127.0.0.1:$HTTP_PORT."
fi
say "Done: $IMAGE answers on 127.0.0.1:$HTTP_PORT"

# The portal for teachers, students and guardians (the portal part, behind the web part under /ar and /en).
if command -v curl >/dev/null 2>&1; then
    portal=""
    for _ in $(seq 1 20); do
        portal="$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "http://127.0.0.1:$HTTP_PORT/ar/sign-in" 2>/dev/null || true)"
        [ "$portal" = 200 ] && break
        sleep 2
    done
    if [ "$portal" = 200 ]; then
        echo "    The portal (teachers, students, guardians) answers under /ar and /en."
    else
        echo "    !! The portal does not answer (HTTP ${portal:-none}): docker compose logs portal"
    fi
fi

# Is it the app that answers for the domain, or still something else? Its own root
# says {"app":"<APP_NAME>",...}; any other answer (a redirect, another site) is not it.
marker="\"app\":\"$(setting APP_NAME | tr -d '"')\""
edge="$( { curl -sk --max-time 5 --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN/"; \
           curl -s --max-time 5 -H "Host: $DOMAIN" "http://127.0.0.1/"; } 2>/dev/null || true)"
if grep -qF "$marker" <<<"$edge"; then
    echo "    https://$DOMAIN reaches it through the server's web server."
else
    echo "    The server's web server does not pass $DOMAIN to it yet: run setup-nginx.sh once, with sudo (README.md)."
fi
home="$(setting PLATFORM_HOME_ACADEMY)"
if [ -n "$home" ]; then
    echo "    Academy \"$home\": https://$DOMAIN/admin and the portal https://$DOMAIN/ar (PLATFORM_HOME_ACADEMY) · others: https://<slug>.$DOMAIN · console: https://$DOMAIN/console"
else
    echo "    Academies: https://<slug>.$DOMAIN/admin, portal https://<slug>.$DOMAIN/ar · console: https://$DOMAIN/console"
fi
[ "$DEMO_DATA" = 1 ] && echo "    Demo sign-in: owner@demo.test; the password is DEMO_PASSWORD in $HERE/.env"
exit 0

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

for f in compose.yml roles.sql postgres-init.sh postgres-start.sh pg_hba.conf backup.sh offsite.sh console.sh; do
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
# Every setting this version reads on a server, with its default (secrets made
# fresh each time it is printed): the first deploy writes all of it, and a later
# one adds what an older .env lacks (below). Each is explained in README.md, "Settings".
port=""
demo_gateway=false
[ "$DEMO_DATA" = 1 ] && demo_gateway=true
settings() {
    cat <<EOF
# This server's settings for the LMS (infra/deploy/README.md, "Settings"). Written
# by deploy.sh on the first deploy; later deploys only add settings it lacks, at
# the end, and never change a value. Keep it private.
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
# Other web pages allowed to call the API from a browser (comma-separated origins). None:
# the portal calls it from its own server, and the apps are not browsers.
CORS_ALLOWED_ORIGINS=

# The apps: the oldest version still let in (older ones are asked to update), and their store pages.
APP_MIN_VERSION=1.0.0
APP_ANDROID_URL=
APP_IOS_URL=

# How many workers take the queue (notifications first) and PHP threads answer the web.
QUEUE_WORKERS=2
WEB_PHP_THREADS=8

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
# Pages whose browsers may open a live connection (host patterns, comma-separated). Any: the
# apps send no origin, academies may have their own domains, and every private channel needs
# a signature only the API gives, to its own signed-in pages.
REVERB_ALLOWED_ORIGINS=*

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
# Seconds before a mail server that stops answering lets go of the worker.
MAIL_TIMEOUT=15

# WhatsApp, texts and push: in the log until the platform's accounts are set here
# (README.md, "Settings"), then docker compose up -d. WhatsApp: Meta's Cloud API
# (WHATSAPP_DRIVER=meta, the business number's id and token, the approved templates).
# Texts: Twilio (SMS_DRIVER=twilio). Push: Firebase (PUSH_DRIVER=fcm, the project id,
# and the service-account file at FCM_CREDENTIALS_FILE, read-only in the worker).
WHATSAPP_DRIVER=log
WHATSAPP_META_TOKEN=
WHATSAPP_META_PHONE_NUMBER_ID=
WHATSAPP_META_TEMPLATE=academy_notification
WHATSAPP_META_OTP_TEMPLATE=sign_in_code
SMS_DRIVER=log
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_FROM=
TWILIO_MESSAGING_SERVICE_SID=
PUSH_DRIVER=log
FCM_PROJECT_ID=
FCM_CREDENTIALS_FILE=./secrets/fcm.json

# Lessons by an outside link until LiveKit is set up for this server: then
# CLASSROOM_PROVIDER=livekit with the LiveKit project's addresses (LIVEKIT_URL for
# browsers and apps, LIVEKIT_API_URL for the server) and its API key and secret.
# Blank, academies cannot choose LiveKit and its webhooks are refused.
CLASSROOM_PROVIDER=external
LIVEKIT_URL=
LIVEKIT_API_URL=
LIVEKIT_API_KEY=
LIVEKIT_API_SECRET=
# Recording lessons (when an academy turns it on) needs LiveKit Egress too: it
# writes into the same S3 bucket (RECORDINGS_DISK=s3 with AWS_*, once the image
# has the S3 adapter), or into a volume the app also reads
# (RECORDINGS_RECORDER_ROOT: the app's recordings folder as Egress sees it).
LIVEKIT_EGRESS_URL=
RECORDINGS_DISK=local
RECORDINGS_RECORDER_ROOT=
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false

# Academies paid through their own Stripe account connected to the platform's
# (Stripe Connect): the platform's secret key, Connect client id and Connect
# webhook's signing secret, and the platform's fee in basis points (0: none).
STRIPE_PLATFORM_SECRET=
STRIPE_CONNECT_CLIENT_ID=
STRIPE_CONNECT_WEBHOOK_SECRET=
STRIPE_APPLICATION_FEE_BP=0

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

# Malware scanning of uploads (RUNBOOKS.md, "Scanning uploads"): off until the server can spare
# about 1.5 GB of memory for clamd; then COMPOSE_PROFILES=scan and UPLOAD_SCANNER=clamav.
COMPOSE_PROFILES=
UPLOAD_SCANNER=none
UPLOAD_SCAN_REQUIRED=true
CLAMAV_HOST=clamav

# Claude, with the platform's own key: the games studio's designer (console › Game studio,
# GAME_DESIGNER_MODEL: a Claude model id; the most capable one designs best) and the
# academies' helpers (Settings › AI helpers; each academy turns each one on, all off at
# first). Empty: the studio says the designer is not set up, the helpers stay off, and
# everything else still works.
ANTHROPIC_API_KEY=
GAME_DESIGNER_MODEL=
# The helpers (RUNBOOKS.md, "AI helpers"): who writes their words (claude, openai, gemini or
# ollama), or off for all of them; best chosen in the console's Platform settings, from each
# provider's own list. AI_MODEL writes and AI_FAST_MODEL handles the quick, short ones (Claude);
# AI_DAILY_CAP is each academy's calls a day.
AI_DRIVER=claude
AI_MODEL=claude-opus-5-5
AI_FAST_MODEL=claude-haiku-5-5
AI_DAILY_CAP=500
# The other providers, each with its own key and models (empty: not used). OpenAI's key, or a
# key from a Google AI Studio project with billing on (on the free tier Google may use what is
# sent); Ollama: the platform's own server's address (https unless on a private network).
OPENAI_API_KEY=
OPENAI_MODEL=
OPENAI_FAST_MODEL=
GEMINI_API_KEY=
GEMINI_MODEL=
GEMINI_FAST_MODEL=
OLLAMA_URL=
OLLAMA_API_KEY=
OLLAMA_MODEL=
OLLAMA_FAST_MODEL=
# Speech to text for the helpers that listen (a teacher's voice note, a recitation):
# ElevenLabs, with the platform's key (ELEVENLABS_API_BASE=https://api.eu.residency.elevenlabs.io
# keeps the audio in the EU; ELEVENLABS_ZERO_RETENTION=true once the account allows it).
# Empty: those helpers stay off.
ELEVENLABS_API_KEY=
ELEVENLABS_API_BASE=https://api.elevenlabs.io
ELEVENLABS_ZERO_RETENTION=false

# The platform's own privacy notice, terms and account-deletion page (/privacy, /terms,
# /account-deletion on the platform's address; the app stores link to them): the name it goes
# by there and the address people write to about their data. Each academy sets its own.
PLATFORM_LEGAL_NAME=
PRIVACY_CONTACT_EMAIL=

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
}

# First deploy: this server's settings and secrets, written once and kept.
if [ ! -f .env ]; then
    say "First deploy: writing this server's settings (.env, postgres.env, readable by $(id -un) only)"
    for p in $(seq 18080 18099); do
        if ! port_taken "$p"; then port="$p"; break; fi
    done
    [ -n "$port" ] || fail "No free port between 18080 and 18099 on 127.0.0.1. Nothing was changed."

    printf 'POSTGRES_PASSWORD=%s\n' "$(secret)" > postgres.env
    settings > .env
    chmod 600 .env postgres.env
fi

# Settings this version reads that an older .env lacks: added once, at the end, with
# their defaults; a value already there is never changed. Not these, which must match
# what the server already has (the database's passwords, the key the data is
# encrypted with, the demo accounts): a server without them is not one deploy.sh made.
fixed=" IMAGE HTTP_PORT APP_KEY DB_PASSWORD DB_OWNER_PASSWORD DB_PLATFORM_PASSWORD DEMO_PASSWORD DEMO_PLATFORM_2FA_SECRET "
added=""
while IFS= read -r line; do
    key="${line%%=*}"
    case "$fixed" in *" $key "*) continue ;; esac
    grep -qE "^$key=" .env || added="$added$line"$'\n'
done < <(settings | grep -E '^[A-Z][A-Z0-9_]*=')
if [ -n "$added" ]; then
    printf '\n# Added by deploy.sh on %s: settings this version reads, with their defaults (README.md, "Settings").\n%s' \
        "$(date -u +%F)" "$added" >> .env
    say "Settings new to this server, added to .env with their defaults: $(printf '%s' "$added" | cut -d= -f1 | tr '\n' ' ')"
fi

# Push (PUSH_DRIVER=fcm): the platform's Firebase service-account file, read-only in the
# worker (compose.yml), never in git or the image. An empty one stands in until it is put
# there; the worker reads it as www-data, so it is readable by all, in this private folder.
fcm="$(setting FCM_CREDENTIALS_FILE)"
fcm="${fcm:-./secrets/fcm.json}"
case "$fcm" in /*) ;; *) fcm="$HERE/${fcm#./}" ;; esac
case "$fcm" in
    "$HERE"/*)
        mkdir -p "$(dirname "$fcm")"
        [ -e "$fcm" ] || : > "$fcm"
        chmod 644 "$fcm" ;;
    *)
        [ -f "$fcm" ] || fail "FCM_CREDENTIALS_FILE in .env names $fcm, which is not there. Put the service-account file there, or set ./secrets/fcm.json."
        [ -r "$fcm" ] && [ "$(( 0$(stat -c %a "$fcm") & 4 ))" -ne 0 ] \
            || echo "    !! $fcm is not readable by all: the worker (www-data) cannot read it. Keep it in this folder's secrets/ instead." ;;
esac
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

say "Migrations (as lms_owner), then fresh table statistics"
compose run --rm --no-deps -T web migrate

# The Quran's text: fetched from Tanzil once and checked whole, then kept in the storage volume (and its backups).
# Not fetched now (no network, Tanzil down): the deploy goes on, and the console's Platform settings fetches it later.
say "The Quran's text (only if the server has no copy yet)"
compose run --rm --no-deps -T web php artisan curriculum:quran --fetch --if-missing \
    || say "The Quran's text was not fetched this time; fetch it from the console (Platform settings) or: php artisan curriculum:quran --fetch"

if [ "$DEMO_DATA" = 1 ]; then
    say "Demo academies (only if they are not there yet)"
    compose run --rm --no-deps -T web seed-demo
fi

# A demo already there takes what later versions of it add (its Quran lessons' surahs, marked words and hifz maps once
# the text is here; the AI helpers' area), whatever DEMO_DATA says. Nothing on a server without the demo academy.
say "The demo academy, brought up to date (when there is one)"
compose run --rm --no-deps -T web php artisan db:seed --class='Database\Seeders\DemoCatchUpSeeder' --force \
    || say "The demo was not brought up to date this time; the next deploy tries again."

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

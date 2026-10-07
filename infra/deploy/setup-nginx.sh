#!/usr/bin/env bash
# Puts the LMS behind the server's nginx, and touches nothing else there: one
# new site file for DOMAIN and *.DOMAIN, checked with `nginx -t` before the
# reload and taken out again if the check fails. Run once, after the first
# deploy, as root:
#
#   sudo DOMAIN=progma.store bash ~/academy-lms/setup-nginx.sh
#
# Then HTTPS for the names that already point at this server (README.md):
#   sudo certbot --nginx -d progma.store -d www.progma.store -d demo.progma.store
set -euo pipefail

DOMAIN="${DOMAIN:?set DOMAIN, e.g. DOMAIN=progma.store}"
HERE="$(cd "$(dirname "$0")" && pwd)"
NAME=academy-lms

fail() { printf '\n!! %s\n' "$*" >&2; exit 1; }

[ "$(id -u)" = 0 ] || fail "Run it with sudo (it writes one file under /etc/nginx)."
command -v nginx >/dev/null 2>&1 || fail "nginx is not installed here; README.md says what another web server needs. Nothing was changed."
[ -f "$HERE/.env" ] || fail "Deploy first (deploy.sh writes .env with the port). Nothing was changed."
PORT="$(grep -E '^HTTP_PORT=' "$HERE/.env" | tail -n 1 | cut -d= -f2)"
[ -n "$PORT" ] || fail "HTTP_PORT is missing from $HERE/.env."

if [ -d /etc/nginx/sites-available ] && [ -d /etc/nginx/sites-enabled ]; then
    target="/etc/nginx/sites-available/$NAME.conf"
    link="/etc/nginx/sites-enabled/$NAME.conf"
else
    target="/etc/nginx/conf.d/$NAME.conf"
    link=""
fi

# Another site that already answers for this domain is left alone, and so is this one.
others="$(grep -rlsw -- "$DOMAIN" /etc/nginx/sites-enabled /etc/nginx/conf.d 2>/dev/null | grep -v "/$NAME.conf\$" || true)"
[ -z "$others" ] || fail "nginx already has a site for $DOMAIN: $others. Nothing was changed."

backup=""
if [ -f "$target" ]; then
    backup="$(mktemp)"
    cp -p "$target" "$backup"
fi

sed -e "s/__DOMAIN__/$DOMAIN/g" -e "s/__PORT__/$PORT/g" "$HERE/nginx/$NAME.conf" > "$target"
chmod 644 "$target"
[ -z "$link" ] || ln -sfn "$target" "$link"

if ! nginx -t 2>&1; then
    if [ -n "$backup" ]; then
        cp -p "$backup" "$target"
    else
        rm -f "$target"
        [ -z "$link" ] || rm -f "$link"
    fi
    fail "nginx refused the new site; it was taken out again and nginx was not reloaded."
fi
[ -z "$backup" ] || rm -f "$backup"

systemctl reload nginx 2>/dev/null || nginx -s reload
echo "nginx passes $DOMAIN and *.$DOMAIN to 127.0.0.1:$PORT ($target)."
echo "Next, HTTPS: sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN -d demo.$DOMAIN   (names that point here)"

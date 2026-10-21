#!/usr/bin/env bash
# The platform team's steps on this server, from GitHub (.github/workflows/console-owner.yml)
# or by hand from this folder. It runs the deployed version's own commands, nothing else:
#
#   bash console.sh owner    the email on stdin: that console admin becomes an owner (the
#                            platform settings are theirs to change); audited, as
#                            `php artisan platform:admin <email> --owner` is. With a name
#                            and a password on the next two lines, someone not on the team
#                            yet is added as an owner (they set up the authenticator app on
#                            first sign-in); the password goes to the command on its stdin,
#                            never on a command line, and is never printed.
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
PROJECT=academy-lms
cd "$HERE"

fail() { printf '\n!! %s\n' "$*" >&2; exit 1; }

export DOCKER_CONFIG="$HERE/.docker"
if docker info >/dev/null 2>&1; then
    dk() { docker "$@"; }
elif sudo -n docker info >/dev/null 2>&1; then
    dk() { sudo -n env DOCKER_CONFIG="$DOCKER_CONFIG" docker "$@"; }
else
    fail "This user cannot run Docker."
fi
compose() { dk compose -p "$PROJECT" -f "$HERE/compose.yml" "$@"; }
[ -f "$HERE/.env" ] || fail "No .env here: deploy.sh has not set this server up yet."

case "${1:-}" in
    owner)
        IFS= read -r email || true
        IFS= read -r name || true
        IFS= read -r password || true
        [[ "$email" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]] || fail "An email on stdin, please."
        if [ -z "$name" ]; then
            compose run --rm --no-deps -T web php artisan platform:admin "$email" --owner
        else
            [ "${#name}" -le 120 ] && [[ ! "$name" =~ [[:cntrl:]] ]] || fail "A name of 120 characters at most, on one line."
            [ "${#password}" -ge 12 ] || fail "A password of 12 characters or more on the third line, please."
            printf '%s\n' "$password" | compose run --rm --no-deps -T web php artisan platform:admin "$email" "$name" --owner --password-stdin
        fi
        ;;
    *)
        fail "Usage: bash console.sh owner   (the console admin's email on stdin; to add someone, their name and password on the next lines)"
        ;;
esac

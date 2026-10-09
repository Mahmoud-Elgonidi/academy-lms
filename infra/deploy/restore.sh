#!/usr/bin/env bash
# The stack's backups, from this folder on the server (infra/deploy/RUNBOOKS.md):
#
#   ./restore.sh list              what is kept (base backups, dumps, files, WAL)
#   ./restore.sh now               a backup now (the backup part also makes one every day)
#   ./restore.sh check [TIME]      a restore drill: the database as it was at TIME (or as late as
#                                  the backups go) brought up apart from the live one, checked,
#                                  thrown away; the console shows the result
#   ./restore.sh to TIME|latest    put the database back as it was at TIME (UTC, e.g. "2026-10-09 10:15").
#                                  The live database is kept, untouched, in its own volume.
#   ./restore.sh back VOLUME       use that kept volume again (undoes "to")
#
# "to" and "back" stop the app while they switch and ask you to type the
# project's name first. Files (proofs, homework, materials) are not put back
# by "to": they are in the backups' files/ folder when needed (RUNBOOKS.md).
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
PROJECT=academy-lms
cd "$HERE"

say() { printf '\n==> %s\n' "$*"; }
fail() { printf '\n!! %s\n' "$*" >&2; exit 1; }

export DOCKER_CONFIG="$HERE/.docker"
if docker info >/dev/null 2>&1; then
    dk() { docker "$@"; }
elif sudo -n docker info >/dev/null 2>&1; then
    dk() { sudo -n env DOCKER_CONFIG="$DOCKER_CONFIG" docker "$@"; }
else
    fail "This user cannot run Docker. Nothing was changed."
fi
compose() { dk compose -p "$PROJECT" -f "$HERE/compose.yml" "$@"; }
[ -f .env ] || fail "No .env here: run it in the folder deploy.sh set up."
setting() { grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- || true; }
set_setting() {
    if grep -qE "^$1=" .env; then sed -i "s#^$1=.*#$1=$2#" .env; else printf '%s=%s\n' "$1" "$2" >> .env; fi
}
current_volume() { local v; v="$(setting PG_VOLUME)"; printf '%s' "${v:-${PROJECT}_postgres-data}"; }

confirm() {
    printf '%s\nType %s to go on: ' "$1" "$PROJECT"
    local answer=""; read -r answer || true
    [ "$answer" = "$PROJECT" ] || fail "Not confirmed. Nothing was changed."
}

wait_healthy() {
    for _ in $(seq 1 60); do
        [ "$(dk inspect -f '{{.State.Health.Status}}' "$(compose ps -q "$1")" 2>/dev/null)" = healthy ] && return 0
        sleep 2
    done
    compose logs --tail=60 "$1"; fail "$1 did not become healthy."
}

# Stop everything, start Postgres on VOLUME, migrate, start the rest, and back up at once
# (a restored database starts a new timeline: the next restore needs a backup from it).
switch_to() {
    local volume="$1"
    say "Stopping the app and the database"
    compose stop web portal reverb worker scheduler relay backup
    compose stop postgres
    set_setting PG_VOLUME "$volume"
    say "Starting the database from $volume"
    compose up -d postgres
    wait_healthy postgres
    say "Migrations (anything newer than the restored moment)"
    compose run --rm --no-deps -T web migrate
    compose up -d --remove-orphans
    wait_healthy web
    say "A backup of the database as it is now"
    compose run --rm --no-deps -T backup now || echo "    !! the backup had failures: ./restore.sh list, and docker compose logs backup"
}

case "${1:-}" in
    list) compose run --rm --no-deps -T backup list ;;
    now) compose run --rm --no-deps -T backup now ;;
    check) compose run --rm --no-deps -T ${BACKUP_DRILL_SQL:+-e BACKUP_DRILL_SQL} backup drill "${2:-}" ;;
    to)
        when="${2:?usage: restore.sh to TIME|latest}"
        [ "$when" = latest ] && when=""
        previous="$(current_volume)"
        volume="${PROJECT}_pgdata-$(date -u +%Y%m%dT%H%M%SZ)"
        confirm "The live database goes back to ${when:-the latest moment the backups have}. Everything after it is no longer in use (it stays in volume $previous)."
        say "Restoring into $volume"
        dk volume create --label "com.docker.compose.project=$PROJECT" --label com.docker.compose.volume=postgres-data "$volume" >/dev/null
        if ! compose run --rm --no-deps -T -v "$volume:/restore" backup restore-into /restore "$when"; then
            dk volume rm "$volume" >/dev/null 2>&1 || true
            fail "The restore did not come up. Nothing was changed."
        fi
        switch_to "$volume"
        say "Done: the database is the one at ${when:-the latest moment}. The one before is in $previous: ./restore.sh back $previous"
        ;;
    back)
        volume="${2:?usage: restore.sh back VOLUME}"
        dk volume inspect "$volume" >/dev/null 2>&1 || fail "There is no volume $volume."
        confirm "The live database becomes the one in $volume (the current one, $(current_volume), stays)."
        switch_to "$volume"
        say "Done: the database is the one in $volume."
        ;;
    *) sed -n '2,17p' "$0"; exit 2 ;;
esac

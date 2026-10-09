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
# From the off-site copy (the offsite part; the private key in a file only you can read):
#
#   ./restore.sh offsite                   what is in the bucket
#   ./restore.sh check offsite KEYFILE     a restore drill from the bucket: the latest it has, fetched,
#                                          decrypted, brought up and checked apart from everything, thrown away
#   ./restore.sh fetch KEYFILE [TIME]      the newest base backup before TIME (or the newest), its WAL, dump,
#                                          files and the settings of then into the volume academy-lms_offsite-restore,
#                                          and those settings next to this file as .env.offsite and postgres.env.offsite
#   ./restore.sh to TIME|latest offsite    "to", from what fetch brought (the server is lost: RUNBOOKS.md)
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
FETCHED="${PROJECT}_offsite-restore"
key_file() {
    local key="${1:?usage: the file with the private key, e.g. ~/offsite.key}"
    [ -r "$key" ] || fail "Cannot read the key file $key."
    grep -q '^AGE-SECRET-KEY-' "$key" || fail "$key holds no age private key (AGE-SECRET-KEY-...)."
    printf '%s' "$(cd "$(dirname "$key")" && pwd)/$(basename "$key")"
}
# The off-site copy's latest (or TIME's) base backup and all it needs, decrypted into VOLUME.
fetch_into() {
    local volume="$1" key="$2" when="${3:-}"
    dk volume rm "$volume" >/dev/null 2>&1 || true
    dk volume create --label "com.docker.compose.project=$PROJECT" "$volume" >/dev/null
    compose run --rm --no-deps -T -v "$volume:/fetched" -v "$key:/run/offsite.key:ro" offsite fetch /fetched "$when"
}

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
    compose stop web portal reverb worker scheduler relay backup offsite
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
    check)
        if [ "${2:-}" = offsite ]; then
            key="$(key_file "${3:-}")"
            drill_volume="${PROJECT}_offsite-drill"
            trap 'dk volume rm "$drill_volume" >/dev/null 2>&1 || true' EXIT
            say "Fetching the latest from the off-site copy"
            fetch_into "$drill_volume" "$key"
            say "Restore drill from it"
            compose run --rm --no-deps -T -e BACKUP_DIR=/fetched -e BACKUP_DRILL_FROM=off-site ${BACKUP_DRILL_SQL:+-e BACKUP_DRILL_SQL} \
                -v "$drill_volume:/fetched" backup drill
        else
            compose run --rm --no-deps -T ${BACKUP_DRILL_SQL:+-e BACKUP_DRILL_SQL} backup drill "${2:-}"
        fi
        ;;
    offsite) compose run --rm --no-deps -T offsite list ;;
    fetch)
        key="$(key_file "${2:-}")"
        when="${3:-}"; [ "$when" = latest ] && when=""
        say "Fetching from the off-site copy into the volume $FETCHED"
        fetch_into "$FETCHED" "$key" "$when"
        # The settings of then, with this server's own image, port and database volume.
        for f in env postgres.env; do
            out=".$f.offsite"; [ "$f" = env ] && out=".env.offsite"; [ "$f" = postgres.env ] && out="postgres.env.offsite"
            if compose run --rm --no-deps -T -v "$FETCHED:/fetched:ro" offsite show /fetched "$f" > "$out.part" 2>/dev/null && [ -s "$out.part" ]; then
                chmod 600 "$out.part"; mv "$out.part" "$out"
            else
                rm -f "$out.part"
            fi
        done
        if [ -f .env.offsite ]; then
            for k in IMAGE HTTP_PORT PG_VOLUME; do
                v="$(setting "$k")"
                grep -v "^$k=" .env.offsite > .env.offsite.part || true
                [ -z "$v" ] || printf '%s=%s\n' "$k" "$v" >> .env.offsite.part
                chmod 600 .env.offsite.part; mv .env.offsite.part .env.offsite
            done
            say "Fetched. The settings of then are in .env.offsite and postgres.env.offsite (this server's IMAGE, HTTP_PORT and PG_VOLUME kept)."
            echo "    The server is lost and this is the new one: mv .env.offsite .env && mv postgres.env.offsite postgres.env,"
            echo "    then ./restore.sh to ${3:-latest} offsite (RUNBOOKS.md, \"The server is lost\")."
        else
            say "Fetched (the bucket had no settings). Next: ./restore.sh to ${3:-latest} offsite"
        fi
        ;;
    to)
        when="${2:?usage: restore.sh to TIME|latest [offsite]}"
        [ "$when" = latest ] && when=""
        from=()
        if [ "${3:-}" = offsite ]; then
            dk volume inspect "$FETCHED" >/dev/null 2>&1 || fail "Nothing fetched yet: ./restore.sh fetch KEYFILE ${2}"
            from=(-e BACKUP_DIR=/fetched -v "$FETCHED:/fetched")
        fi
        previous="$(current_volume)"
        volume="${PROJECT}_pgdata-$(date -u +%Y%m%dT%H%M%SZ)"
        confirm "The live database goes back to ${when:-the latest moment the ${3:+off-site }backups have}. Everything after it is no longer in use (it stays in volume $previous)."
        say "Restoring into $volume"
        dk volume create --label "com.docker.compose.project=$PROJECT" --label com.docker.compose.volume=postgres-data "$volume" >/dev/null
        if ! compose run --rm --no-deps -T "${from[@]}" -v "$volume:/restore" backup restore-into /restore "$when"; then
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
    *) sed -n '2,27p' "$0"; exit 2 ;;
esac

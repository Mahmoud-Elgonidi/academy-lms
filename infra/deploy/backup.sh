#!/usr/bin/env bash
# The stack's backups (compose.yml, the "backup" part, on the Postgres image;
# infra/deploy/RUNBOOKS.md says how to use them). Postgres itself copies
# every WAL segment into /backups/wal (postgres-start.sh); this adds, once a
# day at BACKUP_AT (UTC):
#
#   base/<stamp>/     a base backup (base.tar.gz, pg_wal.tar.gz): with the WAL
#                     after it, the database at any moment since
#   dumps/            pg_dump of lms (any table, any newer Postgres) and the roles
#   files/            the app's files (proofs, homework, materials, tickets),
#                     recordings left out unless BACKUP_FILES_SKIP says otherwise
#
# kept BACKUP_KEEP_DAYS days (the latest two always), with the WAL the oldest
# kept base backup needs. Every run, and every restore drill, is a row in
# platform_backups, which the platform console reads.
#
#   backup.sh run                 every day at BACKUP_AT (and soon after a start, if the last is old)
#   backup.sh now                 one backup now
#   backup.sh list                what is kept
#   backup.sh drill [TIME]        the database as it was at TIME (or the latest it can be) brought up
#                                 in a throwaway server here, checked, recorded, thrown away
#   backup.sh restore-into DIR [TIME]   the same into DIR, stopped, to become the live data (restore.sh)
set -euo pipefail

DIR="${BACKUP_DIR:-/backups}"
STORAGE="${BACKUP_STORAGE:-/storage}"
AT="${BACKUP_AT:-05:30}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-7}"
SKIP_FILES="${BACKUP_FILES_SKIP:-recordings}"
START_DELAY="${BACKUP_START_DELAY:-600}"
export PGHOST="${PGHOST:-postgres}" PGPORT="${PGPORT:-5432}" PGUSER="${PGUSER:-postgres}" PGDATABASE="${PGDATABASE:-lms}"
export PGPASSWORD="${PGPASSWORD:-${POSTGRES_PASSWORD:-}}"
umask 077

log() { printf '%s %s\n' "$(date -u +%FT%TZ)" "$*"; }
stamp_now() { date -u +%Y%m%dT%H%M%SZ; }
iso_now() { date -u +%FT%TZ; }
size_of() { du -sb "$1" 2>/dev/null | cut -f1; }
# One kept thing per line, oldest first (never a half-written .part).
kept() { [ -d "$DIR/$1" ] || return 0; find "$DIR/$1" -mindepth 1 -maxdepth 1 ! -name '*.part' -printf '%f\n' | sort; }

wait_for_postgres() {
    for _ in $(seq 1 60); do
        pg_isready -q && return 0
        sleep 2
    done
    log "Postgres at $PGHOST:$PGPORT does not answer"
    return 1
}

# A row in platform_backups; a backup is not lost when its row cannot be written.
record() {
    local kind="$1" status="$2" started="$3" size="${4:-}" name="${5:-}" detail="${6:-}"
    psql -X -q -v ON_ERROR_STOP=1 -v kind="$kind" -v status="$status" -v started="$started" -v finished="$(iso_now)" \
        -v size="$size" -v name="$name" -v detail="${detail:0:1000}" >/dev/null 2>&1 <<'SQL' || log "(could not write it in platform_backups)"
insert into platform_backups (kind, status, started_at, finished_at, size_bytes, name, detail)
values (:'kind', :'status', :'started', :'finished', nullif(:'size', '')::bigint, nullif(:'name', ''), nullif(:'detail', ''));
SQL
}

backup_now() {
    wait_for_postgres
    local stamp failed=0 started out dest
    stamp="$(stamp_now)"
    mkdir -p "$DIR/base" "$DIR/dumps" "$DIR/files"
    log "backup $stamp"

    # 1. The base backup, with the WAL it needs inside (so it stands alone too).
    started="$(iso_now)"; dest="$DIR/base/$stamp"
    if out="$(pg_basebackup -D "$dest.part" -Ft -z -X stream --checkpoint=fast -l "academy-lms $stamp" 2>&1)"; then
        mv "$dest.part" "$dest"
        record base ok "$started" "$(size_of "$dest")" "$stamp"
        log "  base backup: $(du -sh "$dest" | cut -f1)"
    else
        rm -rf "$dest.part"; failed=1
        record base failed "$started" "" "$stamp" "$out"
        log "  base backup FAILED: $out"
    fi

    # 2. The logical dump and the roles.
    started="$(iso_now)"; dest="$DIR/dumps/lms-$stamp.dump"
    if out="$(pg_dump -Fc -f "$dest.part" 2>&1 && pg_dumpall --globals-only -f "$DIR/dumps/roles-$stamp.sql" 2>&1)"; then
        mv "$dest.part" "$dest"
        record dump ok "$started" "$(size_of "$dest")" "lms-$stamp.dump"
        log "  dump: $(du -sh "$dest" | cut -f1)"
    else
        rm -f "$dest.part"; failed=1
        record dump failed "$started" "" "lms-$stamp.dump" "$out"
        log "  dump FAILED: $out"
    fi

    # 3. The app's files.
    started="$(iso_now)"; dest="$DIR/files/storage-$stamp.tar.gz"
    if [ -d "$STORAGE" ]; then
        local skip=()
        for s in $SKIP_FILES; do skip+=("--exclude=*/academies/*/$s"); done
        if out="$(tar -czf "$dest.part" "${skip[@]}" -C "$STORAGE" . 2>&1)"; then
            mv "$dest.part" "$dest"
            record files ok "$started" "$(size_of "$dest")" "storage-$stamp.tar.gz"
            log "  files: $(du -sh "$dest" | cut -f1)"
        else
            rm -f "$dest.part"; failed=1
            record files failed "$started" "" "storage-$stamp.tar.gz" "$out"
            log "  files FAILED: $out"
        fi
    fi

    prune
    return "$failed"
}

# Older than BACKUP_KEEP_DAYS goes, the latest two always stay, and the WAL
# archive keeps what the oldest kept base backup needs.
prune() {
    local cutoff oldest start_wal
    cutoff="$(date -u -d "-$KEEP_DAYS days" +%Y%m%dT%H%M%SZ)"
    for what in base dumps files; do
        local all=() n i name
        mapfile -t all < <(kept "$what" | grep -v '^roles-')
        n=${#all[@]}
        for ((i = 0; i < n - 2; i++)); do
            name="${all[$i]}"
            # The stamp is in the name: <stamp>, lms-<stamp>.dump, storage-<stamp>.tar.gz.
            if [[ "$(grep -oE '[0-9]{8}T[0-9]{6}Z' <<<"$name")" < "$cutoff" ]]; then
                rm -rf "${DIR:?}/$what/$name"
                [ "$what" = dumps ] && rm -f "$DIR/dumps/roles-$(grep -oE '[0-9]{8}T[0-9]{6}Z' <<<"$name").sql"
            fi
        done
    done
    find "$DIR/dumps" -name 'roles-*.sql' -mtime "+$KEEP_DAYS" -delete 2>/dev/null || true

    oldest="$(kept base | head -n 1)"
    if [ -n "$oldest" ]; then
        start_wal="$(tar -xzOf "$DIR/base/$oldest/base.tar.gz" backup_label | sed -n 's/^START WAL LOCATION: .*(file \([0-9A-F]*\)).*/\1/p')"
        [ -n "$start_wal" ] && pg_archivecleanup -x .gz "$DIR/wal" "$start_wal"
    fi
    psql -X -q -c "delete from platform_backups where finished_at < now() - interval '180 days'" >/dev/null 2>&1 || true
}

list() {
    for what in base dumps files; do
        echo "$what:"
        kept "$what" | while read -r name; do printf '  %-40s %s\n' "$name" "$(du -sh "$DIR/$what/$name" | cut -f1)"; done
    done
    if [ -d "$DIR/wal" ]; then
        echo "wal: $(find "$DIR/wal" -name '*.gz' | wc -l) segments, $(du -sh "$DIR/wal" | cut -f1)"
    else
        echo "wal: none yet"
    fi
}

as_postgres() {
    if [ "$(id -u)" != 0 ]; then "$@"
    elif command -v gosu >/dev/null 2>&1; then gosu postgres "$@"
    else su -s /bin/bash postgres -c "PATH=$(printf '%q' "$PATH") $(printf '%q ' "$@")"
    fi
}

# The database at TIME (UTC unless it says otherwise; empty: as late as the
# archive goes), from the newest base backup made before it, in DIR, promoted
# and left running on a socket in DIR/.s (nothing on TCP, archiving off).
recover() {
    local target="$1" when="${2:-}" base pick=""
    if [ -n "$when" ]; then
        local want; want="$(date -u -d "$when" +%Y%m%dT%H%M%SZ)" || { log "not a time: $when"; return 1; }
        while read -r base; do [[ "$base" < "$want" ]] && pick="$base"; done < <(kept base)
    else
        pick="$(kept base | tail -n 1)"
    fi
    [ -n "$pick" ] || { log "no base backup${when:+ before $when}"; return 1; }
    log "from base backup $pick${when:+, to $when}"

    mkdir -p "$target" "$target.s"
    tar -xzf "$DIR/base/$pick/base.tar.gz" -C "$target"
    tar -xzf "$DIR/base/$pick/pg_wal.tar.gz" -C "$target/pg_wal"
    {
        echo "restore_command = 'gzip -dc $DIR/wal/%f.gz > %p'"
        echo "recovery_target_action = 'promote'"
        # The base backup's own line of history: after "restore.sh to" and "back", the archive also
        # holds the abandoned timeline, which is no child of the newer base backups.
        echo "recovery_target_timeline = 'current'"
        [ -z "$when" ] || echo "recovery_target_time = '$(date -u -d "$when" '+%F %T+00')'"
    } >> "$target/postgresql.auto.conf"
    touch "$target/recovery.signal"
    chown -R postgres:postgres "$target" "$target.s" 2>/dev/null || true
    chmod 700 "$target"

    if ! as_postgres pg_ctl -D "$target" -l "$target.s/log" -w -t 7200 \
        -o "-c listen_addresses='' -c unix_socket_directories='$target.s' -c archive_mode=off -c port=5432" start >/dev/null; then
        if grep -q 'recovery ended before configured recovery target was reached' "$target.s/log"; then
            log "the archive ends before $when: $(sed -n 's/.*last completed transaction was at log time \(.*\)/the last change in it is at \1/p' "$target.s/log" | tail -n 1)"
        else
            tail -n 30 "$target.s/log" >&2
        fi
        return 1
    fi
    for _ in $(seq 1 3600); do
        [ "$(psql -X -h "$target.s" -p 5432 -U postgres -d postgres -Atc 'select pg_is_in_recovery()')" = f ] && break
        sleep 1
    done
    [ "$(psql -X -h "$target.s" -p 5432 -U postgres -d postgres -Atc 'select pg_is_in_recovery()')" = f ] \
        || { tail -n 30 "$target.s/log" >&2; log "still recovering after an hour"; return 1; }
    # Recovery is done: its settings go, so they never run again by chance.
    psql -X -q -h "$target.s" -p 5432 -U postgres -d postgres \
        -c 'alter system reset restore_command' -c 'alter system reset recovery_target_action' -c 'alter system reset recovery_target_time' \
        -c 'alter system reset recovery_target_timeline' >/dev/null
}

stop_server() { as_postgres pg_ctl -D "$1" -m fast -w stop >/dev/null 2>&1 || true; }

drill() {
    local when="${1:-}" work="${BACKUP_DRILL_DIR:-/tmp/drill}" started summary extra="" label
    # "off-site: …" when the backups drilled were fetched from the off-site copy (restore.sh check offsite).
    label="${BACKUP_DRILL_FROM:+$BACKUP_DRILL_FROM: }${when:-latest}"
    started="$(iso_now)"
    rm -rf "$work" "$work.s"
    trap 'stop_server "'"$work"'"; rm -rf "'"$work"'" "'"$work"'.s"' EXIT
    if ! recover "$work" "$when"; then
        record drill failed "$started" "" "$label" "the restore did not come up"
        log "DRILL FAILED"; return 1
    fi
    local q="psql -X -h $work.s -p 5432 -U postgres -d lms -Atc"
    local restored academies users invoices lessons
    restored="$($q "select coalesce(to_char(pg_last_xact_replay_timestamp() at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"'), '')")"
    academies="$($q 'select count(*) from academies')"
    users="$($q 'select count(*) from users')"
    invoices="$($q 'select count(*) from invoices')"
    lessons="$($q 'select count(*) from sessions')"
    if [ -n "${BACKUP_DRILL_SQL:-}" ]; then
        extra="$($q "$BACKUP_DRILL_SQL" 2>&1 || true)"
    fi
    # The latest dump reads back too.
    local dump; dump="$(kept dumps | grep '^lms-' | tail -n 1)"
    local dump_ok=no
    [ -n "$dump" ] && pg_restore --list "$DIR/dumps/$dump" >/dev/null 2>&1 && dump_ok=yes

    summary="restored to ${restored:-the base backup}: $academies academies, $users people, $lessons lessons, $invoices invoices; latest dump reads: $dump_ok"
    [ -z "$extra" ] || summary="$summary; check: $extra"
    if [ "$academies" -ge 1 ] && [ "$dump_ok" = yes ]; then
        record drill ok "$started" "" "$label" "$summary"
        log "drill ok: $summary"
    else
        record drill failed "$started" "" "$label" "$summary"
        log "DRILL FAILED: $summary"; return 1
    fi
}

restore_into() {
    local target="${1:?restore-into DIR [TIME]}" when="${2:-}"
    [ -z "$(ls -A "$target" 2>/dev/null)" ] || { log "$target is not empty"; return 1; }
    recover "$target" "$when"
    stop_server "$target"
    rm -rf "$target.s"
    log "the database${when:+ at $when} is in $target, ready to start"
}

case "${1:-run}" in
    now) backup_now ;;
    list) list ;;
    drill) wait_for_postgres; drill "${2:-}" ;;
    restore-into) restore_into "${2:-}" "${3:-}" ;;
    run)
        trap 'exit 0' TERM INT
        mkdir -p "$DIR/base" "$DIR/dumps" "$DIR/files"
        log "backups every day at $AT UTC, kept $KEEP_DAYS days (the latest two always)"
        # A new stack, or a last base backup older than a day: one soon, once things have settled.
        latest="$(kept base | tail -n 1)"
        if [ -z "$latest" ] || [[ "$latest" < "$(date -u -d '-26 hours' +%Y%m%dT%H%M%SZ)" ]]; then
            sleep "$START_DELAY" & wait $!
            backup_now || log "the backup had failures (see above)"
        fi
        while true; do
            now="$(date -u +%s)"; next="$(date -u -d "today $AT" +%s)"
            [ "$next" -gt "$now" ] || next="$(date -u -d "tomorrow $AT" +%s)"
            sleep "$((next - now))" & wait $!
            backup_now || log "the backup had failures (see above)"
        done
        ;;
    *) echo "usage: backup.sh run | now | list | drill [TIME] | restore-into DIR [TIME]" >&2; exit 2 ;;
esac

#!/usr/bin/env bash
# The off-site copy of the backups (compose.yml, the "offsite" part, on the
# app's image; RUNBOOKS.md, "The off-site copy" and "The server is lost").
# What the backup part keeps is on this server and goes with it; this sends
# each piece, as it appears, to a bucket elsewhere (any S3-compatible
# storage), encrypted on the way with age to OFFSITE_AGE_RECIPIENT: public
# keys, so this server can write the copies but never read them. The private
# key stays with the platform team, off the server.
#
#   wal/<segment>.gz.age      each WAL segment, within OFFSITE_EVERY seconds of being archived
#   base/<stamp>/             each base backup (base, pg_wal) and START_WAL, where its WAL begins;
#                             START_WAL goes last, so a base backup without it is not whole
#   dumps/, files/            each dump with its roles, each copy of the app's files
#   config/<stamp>/           the server's .env and postgres.env whenever they change: APP_KEY
#                             decrypts the gateways' keys and the authenticator secrets, and the
#                             restored database has the passwords written there
#
# kept OFFSITE_KEEP_DAYS days (the latest two always; 0: nothing is deleted
# from here, the bucket's own lifecycle rules do it), with the WAL the oldest
# kept base backup needs. What is in the bucket is never written over. Each
# piece sent, and once an hour how far the WAL has gone, is a row in
# platform_backups (kind offsite), which the console's health page reads.
#
#   offsite.sh run                a pass every OFFSITE_EVERY seconds (idle until the bucket and key are set)
#   offsite.sh now                one pass now
#   offsite.sh list               what is in the bucket
#   offsite.sh fetch DIR [TIME]   with the private key in OFFSITE_KEY_FILE (/run/offsite.key): the newest
#                                 base backup made before TIME (UTC; or the newest), the WAL from it on,
#                                 its dump and files, and the settings of then, decrypted into DIR as the
#                                 backup part keeps them (restore.sh fetch, restore.sh check offsite)
#   offsite.sh show DIR NAME      a fetched settings file (env, postgres.env) on stdout
set -euo pipefail

DIR="${BACKUP_DIR:-/backups}"
STATE="${OFFSITE_STATE:-/state}"
SETTINGS="${OFFSITE_SETTINGS_DIR:-/deploy}"
EVERY="${OFFSITE_EVERY:-300}"
KEEP_DAYS="${OFFSITE_KEEP_DAYS:-30}"
KEY="${OFFSITE_KEY_FILE:-/run/offsite.key}"
BUCKET="${OFFSITE_S3_BUCKET:-}"
PREFIX="${OFFSITE_S3_PREFIX-academy-lms}"
REMOTE="off:$BUCKET${PREFIX:+/${PREFIX%/}}"
export PGHOST="${PGHOST:-postgres}" PGPORT="${PGPORT:-5432}" PGUSER="${PGUSER:-postgres}" PGDATABASE="${PGDATABASE:-lms}"
export PGPASSWORD="${PGPASSWORD:-${POSTGRES_PASSWORD:-}}"
umask 077

# The bucket, for rclone, from the environment alone (no config file holds the keys).
export RCLONE_CONFIG_OFF_TYPE=s3 RCLONE_CONFIG_OFF_ENV_AUTH=false RCLONE_CONFIG_OFF_NO_CHECK_BUCKET=true \
    RCLONE_CONFIG_OFF_PROVIDER="${OFFSITE_S3_PROVIDER:-Other}" \
    RCLONE_CONFIG_OFF_ENDPOINT="${OFFSITE_S3_ENDPOINT:-}" RCLONE_CONFIG_OFF_REGION="${OFFSITE_S3_REGION:-}" \
    RCLONE_CONFIG_OFF_ACCESS_KEY_ID="${OFFSITE_S3_ACCESS_KEY:-}" RCLONE_CONFIG_OFF_SECRET_ACCESS_KEY="${OFFSITE_S3_SECRET_KEY:-}" \
    RCLONE_CONFIG_OFF_CHUNK_SIZE=16M RCLONE_CONFIG=/dev/null RCLONE_RETRIES=3 RCLONE_LOW_LEVEL_RETRIES=10

RECIPIENTS=()
for r in ${OFFSITE_AGE_RECIPIENT//,/ }; do RECIPIENTS+=(-r "$r"); done

log() { printf '%s %s\n' "$(date -u +%FT%TZ)" "$*"; }
iso_now() { date -u +%FT%TZ; }
stamp_now() { date -u +%Y%m%dT%H%M%SZ; }
stamp_of() { if [[ "$1" =~ [0-9]{8}T[0-9]{6}Z ]]; then printf '%s\n' "${BASH_REMATCH[0]}"; fi; }
size_of() { du -sb "$1" 2>/dev/null | cut -f1; }
configured() { [ -n "$BUCKET" ] && [ "${#RECIPIENTS[@]}" -gt 0 ]; }
# What the backup part keeps, oldest first (never a half-written .part).
kept() { [ -d "$DIR/$1" ] || return 0; find "$DIR/$1" -mindepth 1 -maxdepth 1 ! -name '*.part' -printf '%f\n' | sort; }
# A WAL file's place, whatever its timeline: the 16 characters after the first 8 (as pg_archivecleanup compares).
wal_place() { [[ "$1" =~ ^[0-9A-F]{24} ]] && printf '%s' "${1:8:16}"; }
# The stamps of what the bucket has under PATTERN (base/*/START_WAL, config/*/env.age), oldest first.
stamps() { local p; for p in "${!SENT[@]}"; do if [[ "$p" == $1 ]]; then stamp_of "$p"; fi; done | sort -u; }

# A row in platform_backups; nothing sent is lost when its row cannot be written.
record() {
    local status="$1" started="$2" size="${3:-}" name="${4:-}" detail="${5:-}"
    psql -X -q -v ON_ERROR_STOP=1 -v status="$status" -v started="$started" -v finished="$(iso_now)" \
        -v size="$size" -v name="$name" -v detail="${detail:0:1000}" >/dev/null 2>&1 <<'SQL' || log "(could not write it in platform_backups)"
insert into platform_backups (kind, status, started_at, finished_at, size_bytes, name, detail)
values ('offsite', :'status', :'started', :'finished', nullif(:'size', '')::bigint, nullif(:'name', ''), nullif(:'detail', ''));
SQL
}

# A row about something that goes on (the WAL, a piece that keeps failing): when its status
# changes, and at most once an hour while it stays the same.
note() {
    local name="$1" status="$2" detail="$3" file="$STATE/rows/${1//\//_}" last_status="" last_at=0 now
    now="$(date -u +%s)"
    mkdir -p "$STATE/rows"
    [ -f "$file" ] && read -r last_status last_at < "$file"
    if [ "$status" != "$last_status" ] || [ $((now - last_at)) -ge 3600 ]; then
        record "$status" "$(iso_now)" "" "$name" "$detail"
        printf '%s %s\n' "$status" "$now" > "$file"
        return 0
    fi
    return 1
}

# ---------------------------------------------------------------------------
# What is in the bucket: read from it at the start and after each prune, then kept here.
declare -A SENT=()
LISTED_AT=0
ERR=""
load_sent() {
    local listing p
    if ! listing="$(rclone lsf -R --files-only "$REMOTE" 2>&1)"; then
        ERR="the bucket does not answer: $(tail -n 3 <<<"$listing")"
        return 1
    fi
    SENT=()
    while IFS= read -r p; do [ -z "$p" ] || SENT["$p"]=1; done <<<"$listing"
    LISTED_AT="$(date -u +%s)"
}

# One file, encrypted on the way. A failure leaves nothing half there (an upload is whole or
# absent; one whose source broke off is deleted).
send() {
    local src="$1" dst="$2" err="$STATE/err"
    [ -z "${SENT[$dst]:-}" ] || return 0
    if { age "${RECIPIENTS[@]}" < "$src" | rclone rcat "$REMOTE/$dst"; } 2>"$err"; then
        SENT["$dst"]=1
        return 0
    fi
    ERR="$dst: $(tail -n 3 "$err" | tr '\n' ' ')"
    rclone deletefile "$REMOTE/$dst" >/dev/null 2>&1 || true
    return 1
}

put_text() { printf '%s\n' "$1" | rclone rcat "$REMOTE/$2" 2>"$STATE/err" && SENT["$2"]=1 || { ERR="$2: $(tail -n 3 "$STATE/err" | tr '\n' ' ')"; return 1; }; }

# Where a local base backup's WAL begins (its backup_label), remembered.
start_wal() {
    local stamp="$1" file="$STATE/start/$1" w
    if [ ! -s "$file" ]; then
        w="$(tar -xzOf "$DIR/base/$stamp/base.tar.gz" --occurrence=1 backup_label 2>/dev/null | sed -n 's/^START WAL LOCATION: .*(file \([0-9A-F]*\)).*/\1/p')"
        [ -n "$w" ] || return 1
        mkdir -p "$STATE/start"
        printf '%s\n' "$w" > "$file"
    fi
    cat "$file"
}

# The pieces the bucket should have: newer than OFFSITE_KEEP_DAYS, or among the latest two of
# their kind (what pruning would take again is not sent).
cutoff() { [ "$KEEP_DAYS" -gt 0 ] && date -u -d "-$KEEP_DAYS days" +%Y%m%dT%H%M%SZ || printf '00000000T000000Z'; }
wanted() {
    local cut; cut="$(cutoff)"
    local all=() n i
    mapfile -t all
    n=${#all[@]}
    for ((i = 0; i < n; i++)); do
        if [ $((n - i)) -le 2 ] || [[ ! "$(stamp_of "${all[$i]}")" < "$cut" ]]; then printf '%s\n' "${all[$i]}"; fi
    done
}

# ---------------------------------------------------------------------------
send_settings() {
    [ -r "$SETTINGS/.env" ] || return 0
    local sum stamp started any=""
    sum="$( { grep -v '^IMAGE=' "$SETTINGS/.env"; cat "$SETTINGS/postgres.env" 2>/dev/null; } | sha256sum | cut -d' ' -f1)"
    for p in "${!SENT[@]}"; do [[ "$p" == config/* ]] && { any=1; break; }; done
    [ -n "$any" ] && [ "$sum" = "$(cat "$STATE/settings.sum" 2>/dev/null)" ] && return 0
    stamp="$(stamp_now)"; started="$(iso_now)"
    if send "$SETTINGS/.env" "config/$stamp/env.age" \
        && { [ ! -r "$SETTINGS/postgres.env" ] || send "$SETTINGS/postgres.env" "config/$stamp/postgres.env.age"; }; then
        printf '%s\n' "$sum" > "$STATE/settings.sum"
        record ok "$started" "$(size_of "$SETTINGS/.env")" "config/$stamp" "the server's settings (.env, postgres.env)"
        log "  sent the settings ($stamp)"
    else
        note config failed "$ERR" || true
        return 1
    fi
}

send_wal() {
    local from="" place name sent=0 latest=""
    # From where the oldest base backup the bucket keeps begins (all of it while there is none).
    local oldest; oldest="$(kept base | wanted | awk 'NR == 1')"
    [ -z "$oldest" ] || from="$(wal_place "$(start_wal "$oldest" || true)" || true)"
    while IFS= read -r name; do
        place="$(wal_place "$name" || true)"
        if [ -n "$from" ] && [ -n "$place" ] && [[ "$place" < "$from" ]]; then continue; fi
        [ -z "${SENT[wal/$name.age]:-}" ] || continue
        if send "$DIR/wal/$name" "wal/$name.age"; then
            sent=$((sent + 1)); latest="$name"
        else
            return 1
        fi
    done < <(find "$DIR/wal" -maxdepth 1 -type f -name '*.gz' -printf '%f\n' 2>/dev/null | sort)
    local count=$(( $(cat "$STATE/wal.count" 2>/dev/null || echo 0) + sent ))
    [ -z "$latest" ] || printf '%s\n' "${latest%.gz}" > "$STATE/wal.latest"
    if note wal ok "$count segment(s) sent in the last hour, up to $(cat "$STATE/wal.latest" 2>/dev/null || echo none)"; then
        echo 0 > "$STATE/wal.count"
    else
        echo "$count" > "$STATE/wal.count"
    fi
    [ "$sent" = 0 ] || log "  sent $sent WAL segment(s), up to $(cat "$STATE/wal.latest")"
}

send_base() {
    local stamp="$1" started start
    [ -z "${SENT[base/$stamp/START_WAL]:-}" ] && [ -f "$DIR/base/$stamp/base.tar.gz" ] || return 0
    started="$(iso_now)"
    if ! start="$(start_wal "$stamp")"; then
        ERR="base/$stamp: no backup_label in base.tar.gz"
    elif send "$DIR/base/$stamp/base.tar.gz" "base/$stamp/base.tar.gz.age" \
        && send "$DIR/base/$stamp/pg_wal.tar.gz" "base/$stamp/pg_wal.tar.gz.age" \
        && put_text "$start" "base/$stamp/START_WAL"; then
        record ok "$started" "$(size_of "$DIR/base/$stamp")" "base/$stamp" "its WAL from $start"
        log "  sent base backup $stamp"
        return 0
    fi
    note "base/$stamp" failed "$ERR" || true
    return 1
}

send_piece() {
    local what="$1" name="$2" started
    [ -z "${SENT[$what/$name.age]:-}" ] || return 0
    started="$(iso_now)"
    if send "$DIR/$what/$name" "$what/$name.age"; then
        # A dump's roles go with it.
        if [[ "$name" == lms-*.dump ]]; then
            local roles; roles="roles-$(stamp_of "$name").sql"
            [ ! -f "$DIR/dumps/$roles" ] || send "$DIR/dumps/$roles" "dumps/$roles.age" || { note "$what/$name" failed "$ERR" || true; return 1; }
        fi
        record ok "$started" "$(size_of "$DIR/$what/$name")" "$what/$name"
        log "  sent $what/$name"
        return 0
    fi
    note "$what/$name" failed "$ERR" || true
    return 1
}

prune() {
    [ "$KEEP_DAYS" -gt 0 ] || return 0
    local cut list="$STATE/prune.list" what p s all=() keep oldest="" start from
    cut="$(cutoff)"
    : > "$list"
    for what in base dumps files config; do
        # The whole ones of a kind, by stamp (a base backup is whole with its START_WAL, a dump with its .dump).
        mapfile -t all < <(for p in "${!SENT[@]}"; do
            case "$what:$p" in
                base:base/*/START_WAL | dumps:dumps/lms-*.dump.age | files:files/* | config:config/*/env.age) stamp_of "$p" ;;
            esac
        done | sort -u)
        keep="$(printf '%s\n' "${all[@]}" | wanted)"
        [ "$what" = base ] && oldest="$(head -n 1 <<<"$keep")"
        for p in "${!SENT[@]}"; do
            [[ "$p" == "$what"/* ]] || continue
            s="$(stamp_of "$p")"
            [ -n "$s" ] && [[ "$s" < "$cut" ]] && ! grep -qx "$s" <<<"$keep" && printf '%s\n' "$p" >> "$list"
        done
    done
    # The WAL before the oldest kept base backup.
    if [ -n "$oldest" ] && start="$(rclone cat "$REMOTE/base/$oldest/START_WAL" 2>/dev/null)" && from="$(wal_place "$start")"; then
        for p in "${!SENT[@]}"; do
            [[ "$p" == wal/* ]] || continue
            s="$(wal_place "${p#wal/}" || true)"
            [ -n "$s" ] && [[ "$s" < "$from" ]] && printf '%s\n' "$p" >> "$list"
        done
    fi
    if [ -s "$list" ]; then
        if rclone delete "$REMOTE" --files-from-raw "$list" 2>"$STATE/err"; then
            log "  pruned $(wc -l < "$list") old piece(s) from the bucket"
        else
            note prune failed "$(tail -n 3 "$STATE/err" | tr '\n' ' ')" || true
        fi
    fi
    date -u +%s > "$STATE/pruned.at"
    load_sent
}

pass() {
    local failed=0 name
    mkdir -p "$STATE"
    if [ "$LISTED_AT" = 0 ] && ! load_sent; then
        note wal failed "$ERR" && log "OFF-SITE FAILED: $ERR"
        return 1
    fi
    send_settings || failed=1
    if ! send_wal; then
        note wal failed "$ERR" && log "OFF-SITE FAILED: $ERR"
        return 1
    fi
    while IFS= read -r name; do send_base "$name" || failed=1; done < <(kept base | wanted)
    while IFS= read -r name; do send_piece dumps "$name" || failed=1; done < <(kept dumps | grep '^lms-' | wanted)
    while IFS= read -r name; do send_piece files "$name" || failed=1; done < <(kept files | wanted)
    [ "$failed" = 0 ] || log "OFF-SITE: some pieces failed: $ERR"
    # Once a day: what the bucket no longer needs goes, and what is in it is read again.
    if [ $(( $(date -u +%s) - $(cat "$STATE/pruned.at" 2>/dev/null || echo 0) )) -ge 86400 ]; then
        prune || true
    fi
    return "$failed"
}

# ---------------------------------------------------------------------------
list() {
    rclone lsf -R --files-only --format sp --separator ' ' "$REMOTE" | awk '
        { split($2, part, "/")
          if (part[1] == "wal") { wal++; walsize += $1; if ($2 > last) last = $2; next }
          name = (part[1] == "base" || part[1] == "config") ? part[1] "/" part[2] : $2
          total[name] += $1 }
        END {
          for (name in total) printf "  %-48s %10.1f MB\n", name, total[name] / 1048576 | "sort"
          close("sort")
          printf "  wal: %d file(s), %.1f MB, the latest %s\n", wal, walsize / 1048576, last
        }'
}

# Decrypting with the private key; whatever is written is whole, or not there.
get() {
    local src="$1" dst="$2"
    mkdir -p "$(dirname "$dst")"
    if rclone cat "$REMOTE/$src" | age -d -i "$KEY" > "$dst.part"; then
        mv "$dst.part" "$dst"
    else
        rm -f "$dst.part"
        log "could not fetch $src"
        return 1
    fi
}

fetch() {
    local into="${1:?fetch DIR [TIME]}" when="${2:-}" want pick="" s start from p n
    [ -r "$KEY" ] || { log "no private key at $KEY"; return 1; }
    load_sent || { log "$ERR"; return 1; }
    want=99999999T999999Z
    if [ -n "$when" ]; then want="$(date -u -d "$when" +%Y%m%dT%H%M%SZ)" || { log "not a time: $when"; return 1; }; fi
    while read -r s; do [[ "$s" < "$want" ]] && pick="$s"; done < <(stamps 'base/*/START_WAL')
    [ -n "$pick" ] || { log "the bucket has no whole base backup${when:+ before $when}"; return 1; }
    start="$(rclone cat "$REMOTE/base/$pick/START_WAL")"; from="$(wal_place "$start")"
    log "fetching base backup $pick (its WAL from $start)${when:+, for $when}"

    mkdir -p "$into/base" "$into/dumps" "$into/files" "$into/wal" "$into/config"
    rm -rf "$into/base/$pick.part"
    get "base/$pick/base.tar.gz.age" "$into/base/$pick.part/base.tar.gz"
    get "base/$pick/pg_wal.tar.gz.age" "$into/base/$pick.part/pg_wal.tar.gz"
    rm -rf "${into:?}/base/$pick"; mv "$into/base/$pick.part" "$into/base/$pick"

    # The WAL from there on, of every timeline, and the timelines' histories.
    local wal="$STATE/fetch.wal"; : > "$wal"
    for p in "${!SENT[@]}"; do
        [[ "$p" == wal/*.age ]] || continue
        n="${p#wal/}"; s="$(wal_place "$n" || true)"
        if [ -z "$s" ] || [[ ! "$s" < "$from" ]]; then printf '%s\n' "$n" >> "$wal"; fi
    done
    rclone copy "$REMOTE/wal" "$into/wal.enc" --files-from-raw "$wal" --transfers 8
    n=0
    while IFS= read -r p; do
        if ! age -d -i "$KEY" < "$into/wal.enc/$p" > "$into/wal/${p%.age}.part"; then
            log "could not decrypt wal/$p"; return 1
        fi
        mv "$into/wal/${p%.age}.part" "$into/wal/${p%.age}"
        rm -f "$into/wal.enc/$p"; n=$((n + 1))
    done < <(sort "$wal")
    rm -rf "$into/wal.enc"
    log "  $n WAL file(s)"

    # The dump and the files made with it, and the settings of then.
    [ -z "${SENT[dumps/lms-$pick.dump.age]:-}" ] || get "dumps/lms-$pick.dump.age" "$into/dumps/lms-$pick.dump"
    [ -z "${SENT[dumps/roles-$pick.sql.age]:-}" ] || get "dumps/roles-$pick.sql.age" "$into/dumps/roles-$pick.sql"
    [ -z "${SENT[files/storage-$pick.tar.gz.age]:-}" ] || get "files/storage-$pick.tar.gz.age" "$into/files/storage-$pick.tar.gz"
    # The settings of then: the newest before TIME, else the first after it (they seldom change).
    local settings="" all_settings
    all_settings="$(stamps 'config/*/env.age')"
    while read -r s; do [ -n "$s" ] && [[ "$s" < "$want" ]] && settings="$s"; done <<<"$all_settings"
    [ -n "$settings" ] || settings="$(head -n 1 <<<"$all_settings")"
    if [ -n "$settings" ]; then
        get "config/$settings/env.age" "$into/config/env"
        [ -z "${SENT[config/$settings/postgres.env.age]:-}" ] || get "config/$settings/postgres.env.age" "$into/config/postgres.env"
        log "  the settings of $settings"
    fi
    # The backup part's Postgres (uid 999) reads the WAL as it restores.
    chown -R "${OFFSITE_FETCH_OWNER:-999:999}" "$into"
    log "fetched into $into: base backup $pick, $n WAL file(s)$([ -f "$into/dumps/lms-$pick.dump" ] && echo ', its dump')$([ -f "$into/files/storage-$pick.tar.gz" ] && echo ', its files')"
}

case "${1:-run}" in
    now)
        configured || { log "off: set OFFSITE_S3_BUCKET and OFFSITE_AGE_RECIPIENT in .env"; exit 1; }
        pass
        ;;
    list) list ;;
    fetch) fetch "${2:-}" "${3:-}" ;;
    show)
        case "${3:-}" in env | postgres.env) cat "${2:?show DIR NAME}/config/$3" ;; *) echo "show DIR env|postgres.env" >&2; exit 2 ;; esac
        ;;
    run)
        trap 'exit 0' TERM INT
        mkdir -p "$STATE"
        if ! configured; then
            log "off-site copy off: set OFFSITE_S3_BUCKET and OFFSITE_AGE_RECIPIENT in .env (RUNBOOKS.md)"
            sleep infinity & wait $!
        fi
        if ! printf '' | age "${RECIPIENTS[@]}" >/dev/null 2>"$STATE/err"; then
            record failed "$(iso_now)" "" "wal" "OFFSITE_AGE_RECIPIENT is not a public key age takes: $(head -n 1 "$STATE/err")"
            log "OFFSITE_AGE_RECIPIENT is not a public key age takes"
            sleep infinity & wait $!
        fi
        log "off-site copy to $REMOTE every ${EVERY}s, kept $([ "$KEEP_DAYS" -gt 0 ] && echo "$KEEP_DAYS days" || echo "by the bucket's own rules")"
        while true; do
            pass || true
            sleep "$EVERY" & wait $!
        done
        ;;
    *) echo "usage: offsite.sh run | now | list | fetch DIR [TIME] | show DIR env|postgres.env" >&2; exit 2 ;;
esac

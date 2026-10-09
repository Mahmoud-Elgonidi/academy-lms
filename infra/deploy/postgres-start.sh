#!/bin/sh
# How the stack's Postgres starts (compose.yml): the image's own start, with
# the rules in pg_hba.conf and every finished WAL segment copied, compressed,
# into /backups/wal, at least every five minutes while anything changes. With
# the daily base backup (backup.sh) that is a restore to any moment, losing
# five minutes at most.
#
#   postgres-start.sh               start Postgres (as compose does)
#   postgres-start.sh archive P F   Postgres' archive_command for one segment
set -e

if [ "${1:-}" = archive ]; then
    # A segment is written once: the same one again is fine, a different one
    # under the same name is an error (and the segment stays in pg_wal until
    # someone looks). It is written to .part and renamed, so no half file stays.
    path="$2"; name="$3"; target="${BACKUP_WAL_DIR:-/backups/wal}/$name.gz"
    if [ -f "$target" ]; then
        gzip -dc "$target" | cmp -s - "$path"
        exit $?
    fi
    gzip -c "$path" > "$target.part"
    mv "$target.part" "$target"
    exit 0
fi

mkdir -p /backups/wal
chown postgres:postgres /backups /backups/wal
chmod 700 /backups/wal

exec docker-entrypoint.sh postgres \
    -c hba_file=/etc/academy/pg_hba.conf \
    -c wal_level=replica \
    -c archive_mode=on \
    -c archive_timeout=300 \
    -c 'archive_command=sh /etc/academy/postgres-start.sh archive %p %f' \
    "$@"

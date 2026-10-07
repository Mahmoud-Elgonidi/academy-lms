#!/bin/sh
# Runs once, when the database volume is new (Postgres' docker-entrypoint-initdb.d):
# the database "lms" with its three roles, owner (migrations), app (row-level
# security applies) and platform, using the passwords deploy.sh generated.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres \
    -v db=lms \
    -v owner_password="$LMS_OWNER_PASSWORD" \
    -v app_password="$LMS_APP_PASSWORD" \
    -v platform_password="$LMS_PLATFORM_PASSWORD" \
    -f /lms-setup/roles.sql

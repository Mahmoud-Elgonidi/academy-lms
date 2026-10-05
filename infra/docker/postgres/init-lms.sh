#!/bin/sh
# Runs once, when the Postgres container's data directory is first created.
# Creates the dev and test databases with the owner / app / platform roles.
set -e

for db in lms lms_test; do
  psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres \
    -v db="$db" \
    -v owner_password=owner \
    -v app_password=app \
    -v platform_password=platform \
    -f /lms-setup/roles.sql
done

-- Creates the three database roles and the application database.
-- Run once per environment as a superuser:
--
--   psql -v ON_ERROR_STOP=1 \
--        -v db=lms -v owner_password=... -v app_password=... -v platform_password=... \
--        -f database/setup/roles.sql
--
-- Roles:
--   lms_owner     owns every table; used only to run migrations.
--   lms_app       what the application connects as. Not the owner, no BYPASSRLS,
--                 so row-level security applies to every query it makes.
--   lms_platform  BYPASSRLS. Used only by platform code (tenant resolution,
--                 cross-tenant sweepers, reporting) through the pgsql_platform connection.

SELECT format('CREATE ROLE lms_owner LOGIN PASSWORD %L', :'owner_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'lms_owner') \gexec

SELECT format('CREATE ROLE lms_app LOGIN PASSWORD %L NOBYPASSRLS', :'app_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'lms_app') \gexec

SELECT format('CREATE ROLE lms_platform LOGIN PASSWORD %L BYPASSRLS', :'platform_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'lms_platform') \gexec

SELECT format('CREATE DATABASE %I OWNER lms_owner', :'db')
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = :'db') \gexec

\connect :db

REVOKE ALL ON DATABASE :"db" FROM PUBLIC;
GRANT CONNECT ON DATABASE :"db" TO lms_app, lms_platform;
GRANT USAGE ON SCHEMA public TO lms_app, lms_platform;

-- Every table and sequence lms_owner creates is usable by the app and platform roles.
-- Row access is still decided by the RLS policies on each tenant table.
ALTER DEFAULT PRIVILEGES FOR ROLE lms_owner IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO lms_app, lms_platform;
ALTER DEFAULT PRIVILEGES FOR ROLE lms_owner IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO lms_app, lms_platform;
ALTER DEFAULT PRIVILEGES FOR ROLE lms_owner IN SCHEMA public
    GRANT EXECUTE ON FUNCTIONS TO lms_app, lms_platform;

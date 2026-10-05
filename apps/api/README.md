# Academy LMS API

Laravel 13 modular monolith: the REST API (and, later, the Filament back office and queue workers) for the Academy LMS.
The architecture is in [`docs/architecture`](../../docs/architecture/README.md).

## What's here so far (P0, increment 1)

| Module | What it does |
|---|---|
| `modules/SharedKernel` | Tenancy (`TenantContext`, `BelongsToTenant`, fail-closed RLS helpers, tenant carried into queued jobs), local time (`ViewerTimezone`, `LocalTimePresenter`, `LocalTimeResolver` for summer-time edges), RFC 7807 errors, `ar`/`en` locale |
| `modules/Platform` | Academies and their domains; resolves the academy from host, slug or id |
| `modules/Identity` | Users (per academy), fixed roles, Sanctum tokens that carry their academy, login / me / logout |
| `app/` | Composition only: `ResolveTenant` middleware, `academy:create` command |

## Running it locally

Requirements: PHP 8.3+, Composer, PostgreSQL 16+ and Redis (or `docker compose up -d` from the repo root).

```sh
# 1. Database roles + databases (skip if you use docker compose; it does this for you)
psql -U postgres -v ON_ERROR_STOP=1 -v db=lms -v owner_password=owner -v app_password=app -v platform_password=platform -f database/setup/roles.sql
psql -U postgres -v ON_ERROR_STOP=1 -v db=lms_test -v owner_password=owner -v app_password=app -v platform_password=platform -f database/setup/roles.sql

# 2. App
composer install
cp .env.example .env && php artisan key:generate
composer migrate          # = php artisan migrate --database=pgsql_owner
php artisan db:seed       # academy "demo" + one user per role, password "password"
php artisan serve
```

Try it:

```sh
curl -X POST localhost:8000/api/v1/auth/login -H 'X-Academy: demo' -H 'Accept: application/json' \
     -d login=teacher@demo.test -d password=password -d device_name=curl
curl localhost:8000/api/v1/me -H "Authorization: Bearer <token>" -H 'Accept-Language: ar' -H 'X-Timezone: Asia/Dubai'
```

New academy: `php artisan academy:create noor --name-ar="أكاديمية النور" --name-en="Noor Academy" --currency=SAR --timezone=Asia/Riyadh --owner-email=owner@noor.test`

## Tests

```sh
composer test     # Pest, against the lms_test database with the real roles
composer lint     # Pint
```

Feature tests use real Postgres with RLS on, not SQLite: the app connects as `lms_app`, so a missing tenant really returns no rows.

## Rules that keep tenants apart

1. **Three database roles.** The app runs as `lms_app` (RLS applies). Migrations run as `lms_owner` (`composer migrate`); plain `php artisan migrate` fails on purpose. Only Platform code may use `pgsql_platform` (BYPASSRLS); an arch test enforces it.
2. **Every tenant table:** `$table->tenant()`, `$table->tenantKey()`, then `TenantTable::enableRls('table')` in the migration, and `use BelongsToTenant` on the model.
3. **References between tenant tables** use `$table->tenantForeign('user_id', 'users')`, never a plain foreign key: foreign-key checks bypass RLS.
4. **The academy is resolved before auth.** From the bearer token (source of truth), else the host (`{slug}.PLATFORM_DOMAIN` or a verified custom domain), else the `X-Academy` header. A token sent to another academy gets 403.
5. **Modules talk through `Contracts/`.** `tests/Arch` fails the build otherwise.

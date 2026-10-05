# Academy LMS API

Laravel 13 modular monolith: the REST API, the Filament back office and (later) the queue workers for the Academy LMS.
The architecture is in [`docs/architecture`](../../docs/architecture/README.md).

## What's here so far

| Module | What it does |
|---|---|
| `modules/SharedKernel` | Tenancy (`TenantContext`, `BelongsToTenant`, fail-closed RLS helpers, tenant carried into queued jobs), local time (`ViewerTimezone`, `LocalTimePresenter`, `LocalTimeResolver` for summer-time edges), back-office abilities, RFC 7807 errors, `ar`/`en` locale |
| `modules/Platform` | Academies and their domains; resolves the academy from host, slug or id. **Branding**: name, logo, colours, fonts and corners; five built-in templates, custom looks saved as the academy's own templates; `GET /api/v1/branding` |
| `modules/Identity` | Users (per academy), fixed roles mapped to abilities, Sanctum tokens that carry their academy, login / me / logout, per-user preferences, **My settings** page |
| `modules/Catalog` | Subjects (levels and categories come later) |
| `modules/People` | Guardian ↔ student links |
| `modules/Scheduling` | Sessions, participants (double-booking refused by an exclusion constraint), the session log, attendance deadlines (`scheduling:sweep`, every minute) and the **live board** with its actions |
| `app/` | Composition only: `ResolveTenant`, the academy panel provider, `academy:create` |

## The back office

```sh
# .env: PLATFORM_DOMAIN=localhost (browsers send *.localhost to 127.0.0.1)
php artisan filament:assets && php artisan storage:link
composer demo             # fresh database + academy "demo" with an evening of sessions around now
php artisan serve
php artisan schedule:work # optional: runs the attendance sweeper every minute
```

Open http://demo.localhost:8000/admin and sign in as `owner@demo.test` / `password` (or `scheduler@demo.test`).

- **Live operations** (home): sessions now and in the next hour. Two layouts, saved per user: *Table* and *Problems first*. Switch on the board or in *My settings*.
- **Settings › Identity and colours** (owner and admin): templates, colours, fonts, logo, live preview; save as your own template.
- The demo times are relative to when you seeded; run `composer demo` again to get a fresh evening.

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
6. **The back office resolves the academy the same way.** `ResolveTenant` runs first in the panel's middleware, before the session loads the user, and again on every Livewire request.

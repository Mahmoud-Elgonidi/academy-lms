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
| `modules/People` | Guardian ↔ student links; teacher profiles (gender, languages, dialect, accepting new students / trials, contract hours) and the subjects they teach |
| `modules/Credits` | The credit ledger in **minutes**: accounts, lots that expire (paid, makeup, bonus…), one reservation per session, append-only entries with idempotency keys. Reserve / consume / release (refund or a 30-day makeup lot) / expire (`credits:expire`, hourly); `CreditBalanceLow` at the renewal mark (makeup minutes aside) |
| `modules/Enrollment` | The learning agreement (student, subject, teacher, from when): `pending_payment` → `active` on the first payment, teacher assignments and status history. **Enrolled students** list and the **student account** page (credit in sessions and minutes, weekly times, coming sessions, statement, invoices) |
| `modules/Billing` | Price regions, block plans with a price per region, billing accounts (payer: guardian or student), subscriptions, invoices with gapless numbers and VAT (inclusive or added), manual payments. Paying an invoice grants the block's minutes in the same transaction; the renewal invoice goes out by itself on `CreditBalanceLow`. Screens: **Invoices** (due / overdue / paid, record payment, void, printable invoice) and **Plans and prices** (with regions and tax settings) |
| `modules/Scheduling` | Sessions, participants (double-booking refused by an exclusion constraint), the session log, attendance deadlines (`scheduling:sweep`, every minute) and the **live board** with its actions. **Shifts**: versioned weekly schedules (one in force per teacher, exclusion constraint), teacher proposals with counter-offers, comments, notice rules and the impact on booked students; teacher API under `/api/v1/teacher/shifts`. **Find a teacher**: availability from the schedule in force each day (DST-safe) minus bookings and holds, week-by-week coverage, suggested patterns, 10-minute holds, then a free trial or an **enrolment** (`EnrollStudent`: enrollment + credit account + subscription + first invoice + weekly times + sessions, in one transaction). **Weekly times** (`recurring_slots`, on the teacher's clock, exclusion constraint per teacher) become sessions over a rolling horizon (`scheduling:materialize`, nightly); sessions are funded from credit oldest first, and closing a session consumes, refunds or turns its minutes into makeup credit |
| `app/` | Composition only: `ResolveTenant`, the academy panel provider, `academy:create` |

## The back office

The quickest way, from the repository root (Docker only, nothing else to install):

```sh
docker compose up        # then http://localhost:8000/admin, owner@demo.test / password
docker compose exec api composer demo     # a fresh evening of demo sessions
```

Or with PHP 8.3 and Composer on your machine (services from `docker compose up -d postgres redis mailpit`):

```sh
# .env: PLATFORM_DOMAIN=localhost (browsers send *.localhost to 127.0.0.1)
php artisan filament:assets && php artisan storage:link
composer demo             # fresh database + academy "demo" with an evening of sessions around now
php artisan serve
php artisan schedule:work # optional: runs the attendance sweeper every minute
```

Open http://demo.localhost:8000/admin and sign in as `owner@demo.test` / `password` (or `scheduler@demo.test`, `accountant@demo.test`).
With `PLATFORM_DEV_ACADEMY=demo` in `.env`, plain http://localhost:8000/admin works too (local environment only; some browsers do not resolve `*.localhost`).

- **Live operations** (home): sessions now and in the next hour. Two layouts, saved per user: *Table* and *Problems first*. Switch on the board or in *My settings*.
- **Find a teacher**: mark the student's times on the week grid (student's clock), search, pick a suggested pattern or chips, hold for 10 minutes, then book a trial or **enroll**: choose the plan (its price in the payer's region), who pays, and the invoice is issued with the weekly times and their sessions.
- **Invoices**: record a transfer, InstaPay, wallet or cash payment; paid in full, the block's minutes are added and the waiting sessions confirmed in order. Overdue invoices show on the menu badge.
- **Enrolled students** › a student account: credit left in sessions and minutes, makeup credit, the statement line by line. Ziad's shows six lessons used, a teacher no-show turned into makeup credit, and the renewal invoice that went out by itself.
- **Settings › Plans and prices** (owner and accountant): block plans, a price per region, price regions, VAT and invoice numbering.
- **Shift proposals**: Sara's proposal clashes with three booked students; resolve each (agreed exception or start later), reply in the thread, approve. Ahmed's only adds time; Mona's counter-offer waits on her.
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

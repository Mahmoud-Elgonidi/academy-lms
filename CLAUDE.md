# Academy LMS — notes for Claude

Multi-tenant SaaS LMS for online 1:1 tutoring academies. Architecture (Arabic): `docs/architecture/`. Read the relevant section before changing a module.

## Layout
- `apps/api` — Laravel 13 modular monolith. Modules in `apps/api/modules/<Name>/{src,database,routes,lang,config}`, PSR-4 `Modules\<Name>\` → `src/`. `app/` is composition only.
- `docs/architecture` — the design (features, domain rules, technical architecture, patterns, roadmap).
- Planned: `apps/web` (Next.js), `apps/mobile` (Flutter), `packages/*`.

## Commands (run in `apps/api`)
- `composer migrate` — migrations run as `lms_owner`; plain `php artisan migrate` is meant to fail.
- `composer test` — Pest against Postgres `lms_test` with real roles and RLS. Needs Postgres + the roles from `database/setup/roles.sql` (or `docker compose up -d` at the repo root).
- `composer lint` / `vendor/bin/pint` — code style.

## Rules
- Tenant tables: `$table->tenant()` + `$table->tenantKey()` + `TenantTable::enableRls()`; models `use BelongsToTenant`; cross-table references via `$table->tenantForeign()`.
- Never use the `pgsql_platform` connection outside `modules/Platform` (arch test).
- Cross-module calls go through `Contracts/` (interfaces, DTOs, events); arch tests enforce the dependency graph in `docs/architecture/04-design-patterns.md`.
- Use cases are `final` Action classes; controllers, Filament pages and jobs call the same Action.
- API errors are RFC 7807 (`Modules\SharedKernel\Http\ProblemDetails`); user-facing strings in `lang/{ar,en}` inside the module.
- UTC everywhere, `CarbonImmutable`; money as minor units + currency (when Billing lands).

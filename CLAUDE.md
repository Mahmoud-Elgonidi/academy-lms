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
- Back office: Filament 5 panel `academy` in `app/Providers/Filament`; each module adds screens through its own `Filament/*Plugin`. Check permissions with `Gate::allows(Ability::X->value)` (`Modules\SharedKernel\Authorization\Ability`); only Identity maps roles to abilities. Per-user screen choices: declare with `UserPreferences::define()` in the module's provider; they show up on "My settings".
- Custom screens use Filament's colour variables (`--primary-600`, `--danger-50`, ...) so they follow the academy's branding; never hard-code the brand colour.
- `sessions` is Scheduling's table of lessons; Laravel's web sessions live in `http_sessions`.
- Notifications: declare templates in the module's provider with `NotificationsApi::define(new TemplateDefinition(...))` (wording in the module's `lang/{ar,en}/notifications.php`), send with `NotificationsApi::send(new Message(...))` from a listener on the module's own events; pass instants, `Day`, `Money`, `Localized` and `Translatable` values so each recipient gets their language and time zone. Register where it opens in the panels with `NotificationLinks`.
- Live lessons: Classroom owns rooms and video providers (`VideoProvider`: LiveKit, outside link); attendance is Scheduling's (`attendance_segments`), written only through `AttendanceApi`. Links for people without a web login are signed per person (`NotificationLinks::registerPersonal`).
- Live screens: no `wire:poll` for data that changes in the database. A table trigger raises `pg_notify`, a relay command broadcasts a data-less signal on a private channel (authorised in the owning module's provider), and the page refreshes on it. Time-driven changes go to the browser as the next moment to refresh. See `scheduling:live` and the live board.
- Times leave the API only through `LocalTimePresenter::present()` → `{utc, local, timezone, label}` in the viewer's zone (`ViewerTimezone`: `X-Timezone` header → profile → academy → UTC). Wall-clock → UTC only through `LocalTimeResolver` (DST gap → later by the gap; repeated hour → earlier). Timezones are IANA names, never offsets.

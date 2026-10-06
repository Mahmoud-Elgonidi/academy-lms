# Academy LMS API

Laravel 13 modular monolith: the REST API, the Filament back office and (later) the queue workers for the Academy LMS.
The architecture is in [`docs/architecture`](../../docs/architecture/README.md).

## What's here so far

| Module | What it does |
|---|---|
| `modules/SharedKernel` | Tenancy (`TenantContext`, `BelongsToTenant`, fail-closed RLS helpers, tenant carried into queued jobs), local time (`ViewerTimezone`, `LocalTimePresenter`, `LocalTimeResolver` for summer-time edges), back-office abilities, RFC 7807 errors, `ar`/`en` locale |
| `modules/Platform` | Academies and their domains; resolves the academy from host, slug or id. **Branding**: name, logo, colours, fonts and corners; five built-in templates, custom looks saved as the academy's own templates; `GET /api/v1/branding` |
| `modules/Identity` | Users (per academy), fixed roles mapped to abilities, Sanctum tokens that carry their academy, login / me / logout, per-user preferences, **My settings** page |
| `modules/Communication` | Notifications. Modules declare templates (`NotificationsApi::define`, wording in their own lang files) and send by template key; each person gets the message in their language and time zone, in their inbox and on the outside channels the academy has on and they want: email (Mailpit under docker compose), WhatsApp (Meta Cloud API, one approved template) and push (FCM HTTP v1), both written to the log locally. Per-category choices and quiet hours are Identity preferences (on "My settings" and the API); WhatsApp and push wait out quiet hours unless urgent (`communication:sweep` releases them). Deliveries go through the `notifications` queue with retries. Screens: the **bell** in both panels, **Message log** (resend), **Message templates** (academy wording, channel switches). API: `/api/v1/notifications`, `/api/v1/notifications/preferences`, `/api/v1/devices` |
| `modules/Catalog` | Subjects (levels and categories come later) |
| `modules/People` | Guardian ↔ student links; teacher profiles (gender, languages, dialect, accepting new students / trials, contract hours) and the subjects they teach |
| `modules/Curriculum` | Programs per subject, versions (draft → published → retired; one published per program) and a tree of levels, units and lessons with a weight, the sessions a lesson usually takes and its mastery rule ("mastered" once, or two scores of 4+). Published lessons are never deleted: progress points at them. Screen: **Curricula** (the program list and a builder: add, edit, reorder, publish) |
| `modules/Credits` | The credit ledger in **minutes**: accounts, lots that expire (paid, makeup, bonus…), one reservation per session, append-only entries with idempotency keys. Reserve / consume / release (refund or a 30-day makeup lot) / expire (`credits:expire`, hourly); `CreditBalanceLow` at the renewal mark (makeup minutes aside) |
| `modules/Enrollment` | The learning agreement (student, subject, teacher, from when): `pending_payment` → `active` on the first payment, teacher assignments and status history, and the program it follows, pinned to its published version (set on enrolment when the subject has one program; `CurriculumAttached`). Its screens live in Scheduling, which owns the actions on them |
| `modules/Billing` | Price regions, block plans with a price per region, billing accounts (payer: guardian or student), subscriptions, invoices with gapless numbers and VAT (inclusive or added), manual payments. Paying an invoice grants the block's minutes in the same transaction; the renewal invoice goes out by itself on `CreditBalanceLow`. Screens: **Invoices** (due / overdue / paid, record payment, void, printable invoice) and **Plans and prices** (with regions and tax settings) |
| `modules/Scheduling` | Sessions, participants (double-booking refused by an exclusion constraint), the session log, attendance deadlines (`scheduling:sweep`, every minute) and the **live board** with its actions. **Shifts**: versioned weekly schedules (one in force per teacher, exclusion constraint), teacher proposals with counter-offers, comments, notice rules and the impact on booked students; teacher API under `/api/v1/teacher/shifts`. **Find a teacher**: availability from the schedule in force each day (DST-safe) minus bookings and holds, week-by-week coverage, suggested patterns, 10-minute holds, then a free trial or an **enrolment** (`EnrollStudent`: enrollment + credit account + subscription + first invoice + weekly times + sessions, in one transaction). **Weekly times** (`recurring_slots`, on the teacher's clock, exclusion constraint per teacher) become sessions over a rolling horizon (`scheduling:materialize`, nightly); sessions are funded from credit oldest first, and closing a session consumes, refunds or turns its minutes into makeup credit. **Policy**: versioned numbers (`policy_versions`) and the default rule table as a pure engine (`Domain\Policy`); cancel and reschedule by who asked and the notice, a per-block quota, managers' waivers, every decision recorded with its version; unpaid enrolled sessions cancelled a day ahead (in `scheduling:sweep`); makeups booked from makeup credit only; moving an enrollment to another teacher. Screens: **Enrolled students** and the **student account** (cancel, move, makeup, change teacher), **Settings › Cancellation policy** |
| `modules/Learning` | After the lesson. `SessionFinalized` opens a **report** (completed) or an **absence note** (student absent), due 12 hours after the end; `learning:sweep` (every 5 minutes) marks overdue reports and missed homework. The report starts from **today's plan** (the first lesson not mastered, the next once it had its sessions, and reviews that are due) and records an outcome and a score per lesson, two ratings, notes for the family and internal notes, and optional **homework** (text, file or voice recording, on the private disk). A **projector** replays the reports through the mastery rules into `enrollment_progress` (with spaced review: 7, 14, 28… days) and `enrollment_positions` (where the student is, %, pace, expected finish). APIs: `/api/v1/teacher/reports`, `/api/v1/teacher/homework`, and for students and guardians `/api/v1/learning/*` (roadmap, reports without internal notes, homework hand-in, file download). Screens: **Reports** follow-up (overdue / in time / to review / reviewed), the **curriculum and progress** section of the student account, and the **teacher area** at `/teach` |
| `modules/Classroom` | The live lesson. A `VideoProvider` per academy (`classroom_settings`): **LiveKit** (access tokens signed here, rooms through its Twirp room service, room name `{academy}_{lesson}`) or each teacher's **outside link** (Zoom, Meet). The classroom opens 10 minutes before the lesson and closes 10 after it; teacher and student publish, a guardian takes the student's seat, a supervisor joins hidden. LiveKit webhooks (`POST /webhooks/livekit`, signed, deduplicated in `provider_webhook_inbox`) and `classroom:reconcile` (every minute, against the room's participant list) write attendance through Scheduling's `AttendanceApi` into `attendance_segments`; taught minutes are the overlap of teacher and student inside the lesson. The **class page** `/class/{lesson}` (device check, audio-only, screen share, reconnect, today's plan beside the video for the teacher) takes a signed per-person link from the reminder and "join now" messages, so students and guardians need no account on the web. API: `POST /api/v1/lessons/{id}/join` (a ticket for the apps). Screens: **My lessons today** in the teacher area (and the outside link), **Settings › Classroom**, and "observe" / "send the link again" on the live board |
| Live board updates | No polling. Triggers on `sessions`, `session_participants` and `session_events` raise `pg_notify('lms_board', academy_id)`, delivered on commit, with repeats in one transaction folded into one. **`scheduling:live`** listens and folds bursts (at most one signal a second per academy). It broadcasts a data-less `BoardChanged` on `private-academy.{id}.board` (people of that academy with `ViewLiveBoard`), plus "alive" every minute and "resync" on start on the public `lms.live`. The board refreshes on a signal, and at the next moment the clock alone changes it (`BoardClassifier::nextChange`); minute counters run in the browser. It falls back to every 30 seconds while there is no connection or the relay is quiet. Echo is turned on for that page only (`SharedKernel\Realtime\BrowserEcho`) |
| `app/` | Composition only: `ResolveTenant`, the academy and teacher panel providers, `academy:create` |

## The back office

The quickest way, from the repository root (Docker only, nothing else to install):

```sh
docker compose up        # then http://localhost:8000/admin, owner@demo.test / password
docker compose exec api composer demo     # a fresh evening of demo sessions
```

After a `git pull` there is nothing to do (the updates that add the live classroom and live board updates are the exception: restart `docker compose up` once so `livekit`, Reverb on port 8080 and `scheduling:live` start): a running container notices within seconds and runs `infra/docker/api/sync.sh` again (packages when `composer.json` or `composer.lock` changed, new migrations, and the demo data rebuilt when `database/demo-version` changed); the log says `Up to date.` When it is not running, `docker compose up` does the same on start. Use `--build` only when the `Dockerfile` changes.

For live screens outside docker, run `php artisan reverb:start` and `php artisan scheduling:live` next to `php artisan serve` (with `BROADCAST_CONNECTION=reverb`; without them the live board refreshes every 30 seconds).

Or with PHP 8.3 and Composer on your machine (services from `docker compose up -d postgres redis mailpit livekit`; LiveKit's webhooks then miss the API, which runs outside docker, and `classroom:reconcile` fills attendance in from the room list within a minute):

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
- **Settings › Cancellation policy** (owner and admin): the numbers, the rule table read with them; each save is a version. On a student account, cancel or move a lesson and the dialog says what the policy will do (credit, teacher pay, the record) before you confirm; book a makeup from makeup credit; change teacher. From a shift proposal, "move to another teacher" opens that dialog with the date filled in.
- **Shift proposals**: Sara's proposal clashes with three booked students; resolve each (agreed exception or start later), reply in the thread, approve. Ahmed's only adds time; Mona's counter-offer waits on her.
- **Curricula** (owner, admin, supervisor): Easy Arabic Grammar, Juz' Amma memorisation and Nour Al-Bayan are published; Everyday English is a draft. Open one to add levels, units and lessons, reorder them, and publish.
- **Reports**: follow-up of lesson reports. Ziad's lesson of last Sunday is overdue; four reports wait for review (one handed in late), the first was reviewed. Open one to read what was covered and review it with a note.
- **Ziad's account** now shows the program, 30% done, where he is (the present tense), the expected finish, the roadmap by level, the latest reports and homework. "Change program" puts a student on another program; progress is rebuilt from the reports.
- **Teacher area** at `/teach` (`khaled@demo.test` / `password`, Ziad's teacher): **My reports** with the overdue one to write, opening on today's plan; **Homework** with Ziad's voice recording to listen to and mark.
- **Live classroom** (LiveKit runs in docker compose): sign in to `/teach` as `nour@demo.test`, **My lessons today** › join Yousef's lesson; in **Communication › Message log** open the "join now" message to Yousef and open its link in a private window (signed in as staff, the same link makes you a hidden observer); on the live board, "observe" joins hidden. Joins and leaves come back as webhooks and show on the live board and the lesson's log.
- **Notifications**: the bell (top bar) has the owner's latest messages; **Communication › Message log** shows everything the demo sent (invoices, reports, homework, cancellations, "join now" for the live board's late students), one WhatsApp held for quiet hours; **Message templates** to reword any message and switch channels. Emails are in Mailpit (http://localhost:8025).
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

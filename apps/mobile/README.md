# Academy LMS app

One Flutter app for every academy and every person in it: guardians, students (in their stage's look: kids, middle, teen) and teachers. The design is on the canvas linked from [`docs/architecture/03`](../../docs/architecture/03-technical-architecture.md#8-التطبيقات); the API it talks to is [`packages/openapi/openapi.json`](../../packages/openapi/openapi.json).

## Run it

```bash
cd apps/mobile
flutter pub get
# The API from `docker compose up` or `php artisan serve` (apps/api):
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8000/api/v1
# Android emulator: http://10.0.2.2:8000/api/v1 · a phone on the same Wi-Fi: http://<your machine's IP>:8000/api/v1
```

Sign in with a code to `salma.guardian@demo.test` (the code is in Mailpit, http://localhost:8025, or `apps/api/storage/logs/laravel.log`), or with **"Have a password?"**: academy code `demo`, then `yousef@demo.test` (kids), `ziad@demo.test` (middle), `maryam@demo.test` (teen), `nour@demo.test` (teacher), password `password`. Add more accounts from **Me** and switch between them without signing in again.

## How it is built

| Folder | What is there |
|---|---|
| `lib/app` | `bootstrap.dart` (wiring), `app.dart` (theme, language, notices), `router/` (one shell per look: `/family`, `/kids`, `/middle`, `/teen`, `/teach`; the open account decides which, a staff-only account goes to `/web-only`), `theme/` (the academy's branding from `GET /branding` turned into `ThemeData` plus `LmsColors` and `LmsShape` per stage), `di/` (the repositories) |
| `lib/core` | `network/` (dio: `X-Academy`, Bearer, `Accept-Language`, `X-Timezone` on every call; RFC 7807 errors as `ApiProblem`; offline retries, and writes that move lessons or money carry one `Idempotency-Key` across them), `auth/` (several accounts in secure storage, the open one in `SessionCubit`), `time/` (the API's `{utc, local, timezone, label}`; shown on the wall clock the API wrote, never converted on the phone), `l10n/` (generated), `state/` (`LoadCubit`) |
| `lib/features/<x>` | `data/` (a typed repository over the API), `domain/` (models from JSON), `presentation/` (pages, widgets, cubits): `auth`, `profile`, `dashboard` (homes for each look), `schedule`, `classroom` (LiveKit in the app, or an outside link), `billing`, `roadmap`, `homework`, `teaching` |
| `tool/strings.py` | Every word of the app in Arabic (Egyptian, as on the web) and English; it writes `lib/core/l10n/arb/*.arb`, then `flutter gen-l10n` |

The API client is written by hand per feature, not generated: a **contract test** (`test/contract`) reads every `_api.get('…')` call in `lib/` and fails if `openapi.json` has no such method and path.

## Check it

```bash
flutter analyze
flutter test            # unit, widget (sign-in, cancelling a lesson against a fake API) and the contract test
dart format --set-exit-if-changed lib test
python3 tool/strings.py && flutter gen-l10n   # after changing any wording
```

CI runs the same (`.github/workflows/mobile.yml`) plus a web build.

## Not yet in the app

Recording voice homework, writing lesson reports and marking homework (teachers do it on `/teach` for now), proposing shifts, the notifications inbox and push (FCM), deep links into a lesson, forced updates, and dark mode.

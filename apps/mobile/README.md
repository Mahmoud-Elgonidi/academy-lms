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

Sign in with a code to `salma.guardian@demo.test` (the code is in Mailpit, http://localhost:8025, or `apps/api/storage/logs/laravel.log`), or with **"Have a password?"**: academy code `demo`, then `yousef@demo.test` (kids), `ziad@demo.test` (middle), `maryam@demo.test` (teen), `nour@demo.test` or `khaled@demo.test` (teachers; Khaled has reports waiting and a recorded homework to mark), password `password`. Add more accounts from **Me** and switch between them without signing in again.

## How it is built

| Folder | What is there |
|---|---|
| `lib/app` | `bootstrap.dart` (wiring), `app.dart` (theme, language, notices), `router/` (one shell per look: `/family`, `/kids`, `/middle`, `/teen`, `/teach`; the open account decides which, a staff-only account goes to `/web-only`), `theme/` (the academy's branding from `GET /branding` turned into `ThemeData` plus `LmsColors` and `LmsShape` per stage), `di/` (the repositories) |
| `lib/core` | `network/` (dio: `X-Academy`, Bearer, `Accept-Language`, `X-Timezone` on every call; RFC 7807 errors as `ApiProblem`, with a type's own members in `extra`; offline retries, and writes that move lessons or money carry one `Idempotency-Key` across them), `auth/` (several accounts in secure storage, the open one in `SessionCubit`), `time/` (the API's `{utc, local, timezone, label}`; shown on the wall clock the API wrote, never converted on the phone), `l10n/` (generated), `state/` (`LoadCubit`) |
| `lib/features/<x>` | `data/` (a typed repository over the API), `domain/` (models from JSON), `presentation/` (pages, widgets, cubits): `auth` (a code that finds the accounts in every academy, or a password; an account with two-step sign-in answers `two-factor-required`, and its authenticator code goes to `auth/two-factor` before the next chosen account), `profile`, `dashboard` (homes for each look), `challenges` (each stage's challenges, the kids' chest and sticker album, collecting rewards, guardians' cheers), `schedule`, `classroom` (LiveKit in the app, or an outside link), `billing` (paying on the academy's gateway page, or sending the receipt of a transfer for the academy to confirm), `roadmap`, `homework` (handing in words, a voice recording or a file), `teaching` (today, week, students, earnings, time off, and the focused tasks over the tabs: lesson reports, marking homework, My hours with shift proposals), `notifications` (bell, inbox, choices, and the update screen), `consent` (a guardian's consent to each child's account: Privacy under "Me", and a nudge on the home while a child waits) |
| `lib/core/push` | Firebase Cloud Messaging behind `PushTokens`; the device is registered for the open account (`POST devices`) and forgotten before signing out. A push carries its `academy` and `user`: tapped, it opens that account first (`openMessage`), and nothing for an account no longer on the phone. A tapped push or a link `academyapp://app/open/{screen}/{id}` goes through `app/router/targets.dart` (`targetOf`: the notification's `{screen, id}` to a place in the open account's look; a tab location opens with `go`, anything else is pushed over it) |
| `lib/core/realtime` | Live screens, as the portal and the panels have: `pusher_socket.dart` (the little of the Pusher protocol Reverb speaks, over `web_socket_channel`: connect, subscribe with the API's signature, ping/pong, come back after the network drops) and `live.dart` (`Live`: one socket on the open account's own channel (`GET realtime`, signed by `POST realtime/auth`, `features/live`), only while the app is in front; a screen reloads on the signals it shows, and every screen catches up when the app comes back or the socket does; without a socket, a reload a minute). A screen's `LoadCubit` takes `refreshOn: context.read<Live>().on({Live.lessons, ...})`, and `refreshAt` for what the clock alone changes (a lesson's room opening and closing) |
| `lib/shared/motion` | How the app moves: `motion.dart` (`appear`/`staggered` entrances, `bob`, `wiggle`, `pulse`, `shimmerIf`, `CountUp`, `Pressable`, each look's spring), `celebration.dart` (confetti, stars or gems over the screen), `juice.dart` (sounds and haptics). Everything stands still when the phone asks for less motion |
| `tool/sounds.py` | Writes the app's five short sounds (`assets/sounds/*.wav`: pop, coin, chest, levelup, cheer); we make them ourselves, nothing third-party |
| `tool/strings.py` | Every word of the app in Arabic (Egyptian, as on the web) and English; it writes `lib/core/l10n/arb/*.arb`, then `flutter gen-l10n` |

The API client is written by hand per feature, not generated: a **contract test** (`test/contract`) reads every `_api.get('…')` call in `lib/` and fails if `openapi.json` has no such method and path.

## Check it

```bash
flutter analyze
flutter test            # unit, widget (sign-in, cancelling a lesson, challenges for each look, a teacher's report, marking and hours, the inbox, push registration, deep links, forced update) against a fake API, the live socket and reloads against a fake WebSocket, and the contract test
dart format --set-exit-if-changed lib test
python3 tool/strings.py && flutter gen-l10n   # after changing any wording
python3 tool/sounds.py                          # after changing a sound
```

CI runs the same (`.github/workflows/mobile.yml`) plus a web build.

## Live screens

With the API's Reverb and relay running (`php artisan reverb:start`, `php artisan live:relay`), an open screen changes by itself when a lesson, homework, a message or a payment changes elsewhere (the web portal, another phone, the back office). Without them the app works the same and catches up each minute, and whenever it comes back to the front.

## Push, links and updates

- **Push** needs a Firebase project per environment; its settings come with the build, nothing is committed:
  ```bash
  flutter build apk --dart-define=API_BASE_URL=… --dart-define=FCM_API_KEY=… --dart-define=FCM_APP_ID=… \
    --dart-define=FCM_SENDER_ID=… --dart-define=FCM_PROJECT_ID=…   # and FCM_VAPID_KEY=… for the web
  ```
  Without them the app has no push and works the same otherwise. The API sends through its FCM channel (`apps/api`, Communication). iOS also needs an APNs key uploaded to Firebase and the Push Notifications capability in Xcode; the web also needs a `web/firebase-messaging-sw.js` with the project's settings (not in the repo, so web builds have the bell and inbox but no push).
- **Google and Apple sign-in** show on the welcome screen and under **Me → Account** when the API has them on for the apps (`GET auth/social`) and the phone can do it. Google needs the platform's OAuth clients with the build (nothing is committed):
  ```bash
  flutter build apk --dart-define=GOOGLE_SERVER_CLIENT_ID=….apps.googleusercontent.com   # the web client, as the API's GOOGLE_CLIENT_ID
  flutter build ipa --dart-define=GOOGLE_SERVER_CLIENT_ID=… --dart-define=GOOGLE_IOS_CLIENT_ID=…
  ```
  Android also needs the app's signing SHA-1 on an Android client in the same Google project; iOS needs the iOS client's reversed id as a URL scheme in `Info.plist`, and the Sign in with Apple capability in Xcode (the API's `APPLE_APP_IDS` lists the bundle id). Apple is only offered on iPhone, iPad and Mac, and neither on the web build. The phone only asks the person: the API checks the ID token and signs in only an account the academy made.
- **Links**: `academyapp://app/open/{screen}/{id}` (Android intent filter, iOS URL type; on the web `/#/open/{screen}/{id}`), the same targets as a notification.
- **Forced update**: `GET /branding` carries `app.min_version` (and the store links) from the API's `APP_MIN_VERSION`, `APP_ANDROID_URL`, `APP_IOS_URL`; an older app opens on the update screen only.

## Not yet in the app

Dark mode.

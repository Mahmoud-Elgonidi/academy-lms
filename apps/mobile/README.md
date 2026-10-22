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
| `lib/app` | `bootstrap.dart` (wiring), `app.dart` (theme, language, notices), `router/` (one shell per look: `/family` (home, lessons, the journey, money, me), `/kids` (my journey, my lessons, talk to your teacher, more), `/middle` (my journey, my lessons, messages, more), `/teen` (my journey, my lessons, homework, reports), `/teach`; the open account decides which, a staff-only account goes to `/web-only`; the places from before the journey (the islands, missions, quests, goals, games) lead to it), `theme/` (the academy's branding from `GET /branding` turned into `ThemeData` plus `LmsColors` and `LmsShape` per stage), `di/` (the repositories) |
| `lib/core` | `network/` (dio: `X-Academy`, Bearer, `Accept-Language`, `X-Timezone` on every call; RFC 7807 errors as `ApiProblem`, with a type's own members in `extra`; offline retries, and writes that move lessons or money carry one `Idempotency-Key` across them), `auth/` (several accounts in secure storage, the open one in `SessionCubit`), `time/` (the API's `{utc, local, timezone, label}`; shown on the wall clock the API wrote, never converted on the phone; a clock is written the reader's way, 12 hours (5:00 م) or 24 (17:00), through `formats.dart` and `wallClock` from `ClockStyle`, which the app sets from the account's `time_format`: their own choice under Me's settings, else the academy's), `l10n/` (generated), `state/` (`LoadCubit`) |
| `lib/features/<x>` | `data/` (a typed repository over the API), `domain/` (models from JSON), `presentation/` (pages, widgets, cubits): `auth` (a code that finds the accounts in every academy, or a password; an account with two-step sign-in answers `two-factor-required`, and its authenticator code goes to `auth/two-factor` before the next chosen account; every step links the platform's privacy notice and terms, `/privacy` and `/terms` on the API's host, since no academy is chosen yet; signed in, My data links the academy's own), `profile`, `dashboard` (the family's home), `journey` (one journey built from the academy's own curriculum, `GET learning/journey/map`: the course's levels as islands and its lessons as stations, the missions (the next lesson, the teacher's homework, a review that is due), the stars, the week's chest and the egg; drawn per look: the kids' map of islands with a station's sheet, middle school's levels with the race against yourself, the calm look (secondary school, adults, or everyone when the academy chose it), and a guardian's view with play limits, the week's glance, cheering and playing together), `games` (what the journey plays and hatches with: a round's page in a WebView that speaks through `LmsHost`, a sandboxed frame on the web build, results, hatching and naming, the creatures' album, a guardian's limit cards; the world's art ported from `shared::games.*` and its sounds from `tool/sounds.py`), `challenges` (the kids' sticker album from the chests, and cheers), `schedule`, `classroom` (LiveKit in the app, or an outside link), `billing` (paying on the academy's gateway page, or sending the receipt of a transfer for the academy to confirm), `roadmap`, `homework` (handing in words, a voice recording or a file, or playing a practice homework's round), `hifz` (the hifz map, `GET learning/hifz`: every page of the mushaf by part in its strength's colour with the key and the counts, and today's review, a page recited and checked word by word where the academy has the check on (the result comes back with the `hifz.changed` signal) or shown and graded by the learner, a card on each look's journey leading to it; a teacher's view of a student's map from the student's sheet, saying what they already know by parts and surahs or taking pages off; both end with the mistakes notebook, `GET learning/mistakes` or the teacher's `teacher/students/{enrollment}/mistakes`: the words marked in the learner's lessons by surah and verse, in the Quran's face, the kids' look's words to practise in gentle colours), `mushaf` (a Quran lesson's shared mushaf, `GET learning/lessons/{session}/mushaf`: the page right to left in Amiri Quran (bundled, OFL), word by word with the surah's name, its basmala from the platform's copy, each verse's number and the attribution; the teacher turns the pages and taps a word to mark it wrong, a hesitation or tajweed, and the student and their guardians follow the teacher's page with the `mushaf.changed` signal, or look elsewhere and go back to it; opened from the lesson (its card or sheet, "افتح المصحف", once the API says the lesson has one) on `/lessons/:id/mushaf`, and in a LiveKit room a tap away from the video, which stays small over it: the teacher's changes reach the room at once as data on the `mushaf` topic, heard only from the seat the server signed as the teacher's), `teaching` (today, week, students, earnings, time off, and the focused tasks over the tabs: lesson reports, marking homework, My hours with shift proposals), `notifications` (bell, inbox, choices, and the update screen), `consent` (a guardian's consent to each child's account: Privacy under "Me", and a nudge on the home while a child waits) |
| `lib/core/push` | Firebase Cloud Messaging behind `PushTokens`; the device is registered for the open account (`POST devices`) and forgotten before signing out. A push carries its `academy` and `user`: tapped, it opens that account first (`openMessage`), and nothing for an account no longer on the phone. A tapped push or a link `academyapp://app/open/{screen}/{id}` goes through `app/router/targets.dart` (`targetOf`: the notification's `{screen, id}` to a place in the open account's look; a tab location opens with `go`, anything else is pushed over it) |
| `lib/core/realtime` | Live screens, as the portal and the panels have: `pusher_socket.dart` (the little of the Pusher protocol Reverb speaks, over `web_socket_channel`: connect, subscribe with the API's signature, ping/pong, come back after the network drops) and `live.dart` (`Live`: one socket on the open account's own channel (`GET realtime`, signed by `POST realtime/auth`, `features/live`), only while the app is in front; a screen reloads on the signals it shows, and every screen catches up when the app comes back or the socket does; without a socket, a reload a minute). A screen's `LoadCubit` takes `refreshOn: context.read<Live>().on({Live.lessons, ...})`, and `refreshAt` for what the clock alone changes (a lesson's room opening and closing) |
| `lib/shared/motion` | How the app moves: `motion.dart` (`appear`/`staggered` entrances, `bob`, `wiggle`, `pulse`, `shimmerIf`, `CountUp`, `Pressable`, each look's spring), `celebration.dart` (confetti, stars or gems over the screen), `juice.dart` (sounds and haptics). Everything stands still when the phone asks for less motion |
| `tool/sounds.py` | Writes the app's sounds (`assets/sounds/*.wav`): five short ones (pop, coin, chest, levelup, cheer), the islands' effects (zoom, back, locked, egg knock and hatch, cheep, star, win) and a music loop for the map and each island's scale; we make them ourselves, nothing third-party |
| `tool/strings.py` | Every word of the app in Arabic (Egyptian, as on the web) and English; it writes `lib/core/l10n/arb/*.arb`, then `flutter gen-l10n`. It opens with the glossary (one word for one thing: المزيد, الفلوس, المساعدة, مدرّس…) and refuses, on every run, the words it replaces, a count outside an Arabic plural, and emoji outside the kids' strings |
| `tool/screens` | Pictures of every look from the web build, for review: `data_test.dart` walks each look's tabs with the tests' fixtures and records what the screens asked for; `shoot.mjs` serves `build/web`, answers the API from that record, signs in by code and taps by what things are called (Flutter's semantics), then pictures each place at phone size into `build/screens/` (never in git), with a side-by-side sheet when ffmpeg is there. Playwright comes from `apps/web` |

The API client is written by hand per feature, not generated: a **contract test** (`test/contract`) reads every `_api.get('…')` call in `lib/` and fails if `openapi.json` has no such method and path.

## Check it

```bash
flutter analyze
flutter test            # unit, widget (sign-in with the privacy notice and terms before it, cancelling a lesson, a substitute turned down, rating, the kids' look without them, joining (an outside link, a guardian's consent), money (paying online, a transfer receipt, a rejected one sent again), the teacher's tabs and time off, asking to delete an account, small phones with large text in the app's own fonts, the journey in each look (an island and a station's round through a stand-in page, the chest, hatching, the race, the calm look, a guardian's limits and playing together, a practice homework, the old places leading to it), the hifz map (a page shown and graded, a page recited and its check coming back, a teacher marking parts and surahs), the shared mushaf (a teacher marking a word and turning the page, a student following and going back to the teacher's page, the room's data heard only from the teacher) and the mistakes notebook, a teacher's report, marking and hours, the inbox, push registration, deep links, forced update, clocks written 12 or 24 hours and choosing it under Me) against a fake API, the live socket and reloads against a fake WebSocket, and the contract test
dart format --set-exit-if-changed lib test
python3 tool/strings.py && flutter gen-l10n   # after changing any wording
python3 tool/sounds.py                          # after changing a sound
```

Pictures for a review (nothing to install beyond `apps/web`'s packages):

```bash
flutter build web --no-web-resources-cdn
flutter test tool/screens/data_test.dart          # what each look's screens ask for, from the fixtures
node tool/screens/shoot.mjs --all --lang ar       # or --look teen --look middle; --lang en
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

## The icon

The icon (an A reaching a gold star, in the product's own green: one build serves every academy, so it carries none of theirs) is drawn from three SVG layers in `tool/icon/`: `background.svg`, `mark.svg` and `monochrome.svg` (the mark in one colour, for Android 13's themed icons). `python3 tool/icon.py` draws every picture from them with headless Chrome (`CHROME=/path/to/chrome` if it is not on the PATH) and writes Android's legacy, round and adaptive icons (`mipmap-*`, put together by `mipmap-anydpi-v26`), every picture iOS's `AppIcon.appiconset/Contents.json` names (without transparency, as the App Store asks), the web icons, the apple-touch-icon and the favicon, and `tool/icon/store/play-512.png` for Google Play. Change a layer, run it and commit what it writes; never edit the PNGs by hand.

## Releases

`.github/workflows/app-release.yml` hands the Android app to testers. On main, once the mobile checks on a commit pass (or when run by hand from the Actions tab, with an optional line for the testers), it:

1. builds release APKs, one per kind of phone (`arm64-v8a` for most phones, `armeabi-v7a` for older ones, `x86_64` for emulators), named after the app and its version (`Academy-v1.0.0-build12-arm64-v8a.apk`: the name under the icon, `pubspec.yaml`'s version, the run's build number);
2. tags the commit `app-v{version}-b{build}` and makes a GitHub pre-release holding the APKs and the notes;
3. posts each phone APK in the testers' Telegram channel as its own post, with the notes under it (`tool/release/notes.sh`: the app's name and version, the server, and what changed since the last release), the kind of phone it is for, cut at a line to fit Telegram's caption with a link to the release for the rest (`tool/release/telegram.sh`; a file over Telegram's 50 MB becomes a message with the link instead).

The APKs also stay on the run for 30 days. Re-running a build (say, once the Telegram secrets are in) keeps its tag and release and posts again. A push to another branch that touches `android/`, `tool/release/` or the workflow builds the APKs and shows the notes and the posts it would make (a dry run) on the run's summary.

What testers read under "what changed" comes from the commits since the last release: a commit says it in Egyptian Arabic with `App-Change:` lines in its last paragraph, with the other trailers (one line per change; `App-Change: -` leaves a commit out):

```
Lessons: join from the lesson card

App-Change: تقدر تدخل الحصة من الكارت على طول
Co-Authored-By: …
```

A commit without them that changes the app (`apps/mobile` without its tests, tools and docs, or `packages/openapi`) is listed by its subject. The first build lists only `App-Change` lines.

Settings (repository → Settings → Secrets and variables → Actions):

| Name | Kind | What |
|---|---|---|
| `ANDROID_KEYSTORE_BASE64`, `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD` | secrets | The release key. Without it the APKs carry the build machine's debug key, which changes every run, so a phone must uninstall one build before it can install the next. |
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID` | secrets | The bot (from @BotFather) and where it posts: a channel (`@name`, or its `-100…` id for a private one; make the bot an admin there) or a person (their id; they press Start on the bot). Without `TELEGRAM_CHAT_ID` the bot posts to the one chat it heard from in the last 24 hours (the channel it was made an admin of, else the one person who pressed Start) and tells that chat its id, to save here so later builds find it too. `TELEGRAM_THREAD_ID` sends into a topic of a group. Without the token the APKs are on the GitHub release only. |
| `FCM_API_KEY`, `FCM_APP_ID`, `FCM_SENDER_ID`, `FCM_PROJECT_ID` | secrets | Push (see above). |
| `APP_API_BASE_URL` | variable | The API the app talks to; default `https://{DEPLOY_DOMAIN}/api/v1` (today `https://lms.progma.store/api/v1`). |
| `GOOGLE_SERVER_CLIENT_ID` | variable | Google sign-in (see above). |

Making the release key, once, on your own machine (keep `release.jks` and its passwords somewhere safe: the stores accept updates only signed with it):

```bash
keytool -genkeypair -v -keystore release.jks -keyalg RSA -keysize 2048 -validity 10000 -alias academy
base64 -w0 release.jks   # → ANDROID_KEYSTORE_BASE64 (on macOS: base64 -i release.jks)
keytool -list -v -keystore release.jks -alias academy | grep SHA1   # for Google sign-in's Android client
```

On a laptop, the same key signs `flutter build apk --release` when `android/key.properties` (`storeFile`, `storePassword`, `keyAlias`, `keyPassword`; never committed) is there; without it the release build uses the debug key.

## Not yet in the app

Dark mode.

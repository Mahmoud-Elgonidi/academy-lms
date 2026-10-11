# Academy LMS portal

The academies' web portal (Next.js 16, App Router), in two parts:

- **The teacher's portal** (phase A, `/teach`): today, the week, lesson reports, marking homework (recordings play in the page), students and their roadmap, trial lessons, my hours (shift proposals with the academy), time off, earnings, performance, notifications, and the live lesson in the browser (LiveKit, or the teacher's outside link).
- **The family's portal** (phase B, `/family`), for students and guardians, in the look of the person signed in, as in the app and the `/my` panel: a student's own stage (kids: their journey map, missions and big tabs; middle school: points, levels and quests; teens: their plan and goals) or the classic look for adults and every guardian. The home, lessons (cancelling after seeing what the policy will do, turning down a substitute, rating a lesson that took place; kids do none of these), progress (each course's roadmap and the teacher's reports), homework (handed in as words, a voice recording made in the browser, or a picture or PDF), money (what is owed, paid on the academy's own gateway page or by hand with the receipt sent for the academy to confirm; invoices with their tax document; lessons left; the classic look only), challenges (the student collects rewards; a guardian follows and cheers), notifications. A guardian with several children picks whose pages they see.

Both portals stay current by themselves (see "Live" below). Staff keep the back office at `/admin`.

It talks only to the API (`/api/v1`, [`packages/openapi/openapi.json`](../../packages/openapi/openapi.json)), as the Flutter app does, and calls the same Actions through it.

## Run it

```bash
# The API with the demo data (apps/api): docker compose up at the root, or
#   composer migrate && php artisan db:seed && php artisan serve
cd apps/web
npm ci
ACADEMY=demo API_INTERNAL_URL=http://127.0.0.1:8000 npm run build
ACADEMY=demo API_INTERNAL_URL=http://127.0.0.1:8000 npm run start   # http://localhost:3000
```

`npm run dev` works the same way. Sign in at http://localhost:3000/ar with `password` as:

- `khaled@demo.test`, a teacher (a report waiting, a recorded homework to mark, a lesson now and then);
- `ziad.guardian@demo.test`, a guardian (an overdue invoice, lessons to cancel or rate), or `yousef.guardian@demo.test`;
- `yousef@demo.test` (kids), `ziad@demo.test` (middle school), `maryam@demo.test` (teen), students in their own looks.

Or sign in with a code: it goes to Mailpit (http://localhost:8025) or `apps/api/storage/logs/laravel.log`.

| Setting | What it is |
|---|---|
| `API_INTERNAL_URL` | where the portal's server reaches Laravel: `http://web:8080` in the production stack, `http://127.0.0.1:8000` on a laptop |
| `ACADEMY` | a laptop only: the academy's slug, sent as `X-Academy` (on a server, the host decides, as for the panels) |

## How it is built

| Where | What is there |
|---|---|
| `src/proxy.ts` | Next's proxy (it was middleware): a page needs the session cookie, except signing in; then next-intl keeps `/ar` or `/en` in the path |
| `src/lib/api/server.ts` | The API from the server only (`server-only`): `get` for pages (401 → sign in again, 403/404 → not found, anything else → the error page), `send` for Server Actions (the API's RFC 7807 words and field errors back to the form). Each call carries the token from the httpOnly cookie, `Accept-Language`, `X-Timezone` (the browser's zone, from a cookie the page writes), and the page's host as `X-Forwarded-Host`. Paths are typed from the OpenAPI document (`schema.ts`): a path or method the API no longer has fails `npm run typecheck` |
| `src/lib/teach/types.ts`, `src/lib/family/types.ts` | What the teacher and family endpoints answer, as the pages read it |
| `src/lib/family` | `context.ts`: the learners, the look of the person signed in (`viewer_stage`) and whose pages they are (the `lms_learner` cookie, else the first); `rules.ts`: what a look allows (money only in the classic look; no cancelling or rating for kids); `nav-items.ts`: each look's places and phone tabs (`nav.{stage}` in messages); `targets.ts`: where a notification opens |
| `src/app/[locale]/sign-in` | Signing in by code (to an email or number) or password, as Server Actions, then the authenticator's code (`auth/two-factor`) for an account with two-step sign-in on (`lib/two-factor.ts`; a back-office role that must still set it up goes to `/admin`); the token goes into an httpOnly, `SameSite=Lax` cookie (`Secure` behind https) and never reaches the page's scripts. Teachers go to `/teach`, students and guardians to `/family`, staff to `/admin` |
| `src/app/[locale]/teach` | The teacher's pages (server components) and `actions.ts` (every write: a report, a mark, a trial assessment, a shift proposal and its messages, time off). After a write the page refreshes from the API |
| `src/app/[locale]/family` | The family's pages, wrapped in the look (`layout.tsx`: a `data-stage` frame with the stage's fonts and corners, the academy's colours either way), and `actions.ts` (choosing the learner, cancelling with an idempotency key, turning down a substitute, rating, handing in as multipart, collecting a reward, cheering, paying, a guardian agreeing to or withdrawing consent to a child's account on `privacy`) |
| `src/lib/actions/shared.ts` | Writes both portals make: a seat in a lesson, notifications read |
| `src/lib/live.ts`, `src/lib/live-server.ts`, `src/components/live-refresh.tsx`, `src/app/bff/realtime/auth` | Live pages (see "Live") |
| `src/app/bff` | The portal's own routes: `session-ended` (forgets a refused token, then sign in again) and `submissions/{id}/file` (a handed-in file or recording, streamed through with the person's token; the API checks it is theirs to see; seeking passes `Range` on) |
| `src/components` | `ui.tsx` (the small kit), `icons.tsx` (our own line icons), `inbox.tsx`, `handed.tsx` (what was handed in), `roadmap-view.tsx`, `teach/*` (shell and navigation, lesson card, report editor, marking, week view and shift editor, time off, classroom), `family/*` (shell, navigation and learner switch, the four homes, lesson card and its dialogs, hand-in with the browser's recorder, paying, the challenges board) |
| `src/app/[locale]/family/games`, `src/components/games`, `src/lib/games` | The islands games (docs/architecture/02, section 18), from the same API as the app and `/my`: a child's map, opened island, round, result, hatching and album; middle school's race; the teens' and adults' review; a guardian's limits and glance. A round plays in `<iframe sandbox="allow-scripts">` (`round.tsx`, `lib/games/player.ts`: only the frame's own messages, answers only for the round's items) from `/bff/games/{version}`, which serves the game's page on the portal's own origin with the API's own headers (its sandbox policy; `next.config.ts` leaves that route out of the portal's set). The drawings (`lib/games/art.ts`, `components/games/*.css`) and the sound (`lib/games/sound.ts`) are ports of `shared::games.*` and the panel's styles: change them together. Starting and finishing a round (one idempotency key per round), naming a creature, the buddy and a guardian's limits are Server Actions in `games/actions.ts` |
| `messages/{ar,en}.json` | Every word; Arabic is Egyptian, as on the panels and in the app |
| `src/app/globals.css` | Tailwind 4 tokens from the academy's branding (`GET /branding`): its colours as CSS variables on `<html>`, tints mixed from them, its corners and fonts; each stage's look (`[data-stage]`: Baloo Bhaijaan 2 for kids, Rubik for middle school, Plex for teens, from `src/fonts`, OFL). Motion (`.appear`, `.stagger`, `.lift`, `.bar-fill`, `.bob`, `.pop`, `.nudge`) only when the person allows motion |

Times are shown as the API writes them (`LocalTime.label`, or the wall clock in `local`); calendar days (a week, a month) are written in UTC so a day stays the same day. The portal never converts zones.

## Live

A page refreshes itself when something it shows changes elsewhere: a lesson booked, moved or cancelled (also from the phone), homework given, handed in or marked, a message arrived or read, a payment settled. The frame (both shells) holds one socket to Reverb, as the panels do:

- `GET /v1/realtime` (in `lib/live-server.ts`, with the unread count for the badge) says where to listen and the person's own channel (`user.{id}`); the socket goes to the page's own host unless the API names another, so on a server it is `wss://<academy host>/app/<key>`, passed to Reverb by the web part.
- The subscription is signed through `/bff/realtime/auth`, which calls `POST /v1/realtime/auth` with the token from the cookie; the page never holds the token, and the API refuses anyone else's channel.
- `components/live-refresh.tsx`: `LiveRefresh` listens for `lessons.changed`, `homework.changed`, `inbox.changed`, `payments.changed`, `practice.changed` (a round, a creature or a limit) and the rest of `SIGNALS` (no data) and the relay's `resync`, then calls `router.refresh()`, which keeps what is being typed. A hidden tab refreshes when it is looked at again; without a socket, or with the relay quiet for 2.5 minutes, a slow timer (a minute) takes over. `RefreshAt` refreshes at the moments the clock alone changes a page (a lesson's room opening, starting, closing), as the panels' `data-live-next` does.

Locally, run Reverb and the relay next to the API (`php artisan reverb:start --port=8080`, `php artisan live:relay`); without them the pages still work and refresh every minute.

## Check it

```bash
npm run lint
npm run typecheck
npm test                 # unit (vitest): days and zones, safe redirects, notification targets, form problems, each look's places and what it allows, the live socket and its timers
npm run api:types        # after the API changes (composer openapi), then commit src/lib/api/schema.ts
npx playwright test      # end to end against the running portal and API with fresh demo data (playwright.config.ts)
E2E_LIVE=1 npx playwright test   # also the live test, with Reverb and the relay running
```

The end-to-end tests sign in (password, code, staff, a refused token), then as Khaled: every page at phone width with no sideways scroll, the week back and forth, a report refused without ratings and then sent with homework, a recording that plays through the portal and a mark, someone else's file refused, time off asked for and withdrawn, a draft of new hours with a message to the academy then withdrawn, and the switch to English. Then the family: as Ziad's guardian every page at phone width, the classic tabs, what cancelling would do (and keeping the lesson), a rating, paying on the gateway's page, a transfer receipt sent (refused without the file first) and waiting for the academy, a cheer; as Yousef (kids) the journey and missions, no money, cancelling or rating, and collecting a reward; as Ziad (middle school) his quests, homework handed in in words, and (with `E2E_LIVE=1`) the teacher's mark arriving on his open page without a reload, the teacher marking it in another browser. They change the demo data, so CI runs them on a fresh demo (`.github/workflows/web.yml`).

## On the server

The portal is built into the one production image (`apps/api/Dockerfile.prod`, a Node stage) and runs as its own part, `portal` (`infra/deploy/compose.yml`). The web part passes it `/ar`, `/en`, `/_next` and `/bff`, and a browser opening the bare address goes to `/ar`; the portal calls the API back through the web part, so the academy is the host's. See [`infra/deploy/README.md`](../../infra/deploy/README.md).

# Deploying to a server

Every push to `main` that passes the API or web checks goes to the server on its own:
GitHub Actions builds the production image (the API, the panels and the teachers'
portal in one image), rehearses the deploy on its own machine, pushes the image to
GitHub's registry, then runs `deploy.sh` on the server over SSH
(`.github/workflows/deploy.yml`).

The LMS is built to share a server with other projects without touching them:

| What | How it stays apart |
|---|---|
| Files | one folder, `~/academy-lms` in the deploy user's home (`/home/lukas-demo/academy-lms` unless `DEPLOY_USER` names another), owned by that user (`.env` and `postgres.env` readable by it only). Signed in as root, use the full path: `~` is root's home there |
| Containers | the Docker project `academy-lms`: its own containers, network (`academy-lms_internal`) and volumes; `deploy.sh` only ever runs `docker compose -p academy-lms` and removes only its own older images |
| Network | one port, on `127.0.0.1` only (the first free one from 18080); Postgres, Redis and Reverb are not published at all |
| Resources | memory and CPU limits on every container (`WEB_MEMORY`, `PORTAL_MEMORY`, `DB_MEMORY`, ... in `.env`), and logs that rotate (3 × 10 MB) |
| Web server | one new nginx site for `progma.store` and `*.progma.store`, added once by `setup-nginx.sh`: it stops if any site already mentions the domain, and takes its file out again if `nginx -t` fails |
| The system | nothing installed or changed: no packages, no Docker settings, no firewall |

## The server, once

1. **Docker** with the compose plugin (`docker compose version`), and the deploy user able to use it:
   in the `docker` group, or with passwordless sudo for `docker`. `deploy.sh` stops and says so otherwise.
2. **DNS** for the domain, pointing at the server:
   `progma.store`, `www.progma.store` and `*.progma.store` (academies are `<slug>.progma.store`).
   Without the wildcard, one academy can live on the domain itself: `PLATFORM_HOME_ACADEMY=<slug>` in the
   server's `.env` serves it at `https://progma.store/admin`, `/teach` and `/my` (the console stays at
   `/console`; a subdomain, token or `X-Academy` header still decides when there is one). Then
   `cd ~/academy-lms && docker compose up -d` to apply it.
3. **An SSH key for GitHub** (better than a password). On your own computer:
   ```bash
   ssh-keygen -t ed25519 -f academy_deploy -N "" -C "github deploy"
   ssh-copy-id -i academy_deploy.pub lukas-demo@145.223.99.175   # or append it to ~/.ssh/authorized_keys
   ssh-keyscan -H 145.223.99.175                                   # the server's key, for DEPLOY_KNOWN_HOSTS
   ```

## GitHub, once

Repository → Settings → Secrets and variables → Actions:

| Secret | Value |
|---|---|
| `DEPLOY_SSH_KEY` | the private key `academy_deploy` (all of it) |
| `DEPLOY_PASSWORD` | only if there is no key: the SSH password |
| `DEPLOY_KNOWN_HOSTS` | the `ssh-keyscan -H` output; without it the server's key is trusted as first seen |
| `DEPLOY_HOST`, `DEPLOY_USER` | only to deploy somewhere else: the workflow defaults to `145.223.99.175` and `lukas-demo` |

The key or the password lives in GitHub's secrets only, never in a file of the repository.

Optional variables: `DEPLOY_DOMAIN` (default `progma.store`), `DEPLOY_DIR` (default `academy-lms`, under the
user's home), `DEPLOY_SSH_PORT` (default 22). Without the key or password the workflow still builds and rehearses,
and says what is missing.

## The first deploy

1. Actions → **deploy** → Run workflow on `main`, with **Load the demo academies** ticked.
   `deploy.sh` writes the server's `.env` (random passwords and keys), starts Postgres and Redis, runs the
   migrations as `lms_owner`, loads the demo, starts the app and checks `/up`.
2. Put it behind the server's nginx, once:
   ```bash
   sudo DOMAIN=progma.store bash ~/academy-lms/setup-nginx.sh
   sudo certbot --nginx -d progma.store -d www.progma.store -d demo.progma.store
   ```
   certbot covers the names given; add each new academy with `--expand -d <slug>.progma.store`, or get one
   wildcard certificate through a DNS challenge (`certbot certonly --manual --preferred-challenges dns
   -d progma.store -d '*.progma.store'`, then point the site at it).
3. Open `https://demo.progma.store/admin` (or `https://progma.store/admin` with `PLATFORM_HOME_ACADEMY=demo`). The demo accounts (`owner@demo.test`, `khaled@demo.test`, ...) use
   this server's own password: `grep DEMO_PASSWORD ~/academy-lms/.env` as the deploy user (as root: `grep DEMO_PASSWORD /home/lukas-demo/academy-lms/.env`). The portal (teachers at /ar/teach, students and guardians at /ar/family, live through Reverb) is
   `https://demo.progma.store/ar` (or `https://progma.store/ar`); a browser opening the bare address goes there. The platform console is
   `https://progma.store/console` (`admin@platform.test`, same password); its authenticator secret is
   `DEMO_PLATFORM_2FA_SECRET` in `.env`, or print the current code:
   `cd ~/academy-lms && docker compose exec web php artisan platform:code admin@platform.test`.
   The owner, admins and accountants set up two-step sign-in (an authenticator app) the first time they
   open `/admin`. The app and the portal ask for its code after the password or the one-time code. If someone
   loses the phone and the recovery codes, the console resets it: Support sign-in → the person → a reason → Reset.

Another web server instead of nginx needs the same as `nginx/academy-lms.conf`: pass `progma.store` and
`*.progma.store` to `127.0.0.1:<HTTP_PORT>` with `Host`, `X-Forwarded-For`, `X-Forwarded-Proto` and the
WebSocket upgrade headers.

## Day to day (in `~/academy-lms`)

```bash
docker compose ps                                  # the ten parts: web, portal, reverb, worker, scheduler, relay, backup, offsite, postgres, redis
docker compose logs -f web                         # or portal, worker, scheduler, ...
docker compose exec web php artisan about
docker compose exec web php artisan platform:health   # every part, the backups and their off-site copy, disk, mail, errors (also /console/health)
docker compose exec web php artisan platform:health --errors   # the errors themselves, for a 500 someone saw
./restore.sh list                                  # the backups; ./restore.sh check is a restore drill
./restore.sh offsite                               # what the off-site copy holds
docker compose exec web php artisan platform:mail-test   # does the server's mail reach the platform team?
```

- **When something is wrong**, or for a restore drill or a restore to a moment: [`RUNBOOKS.md`](RUNBOOKS.md).
- **Backups** are automatic: every change to the database as it happens (at least every 5 minutes), and a
  base backup, a dump and the app's files every day at `BACKUP_AT` (UTC), kept `BACKUP_KEEP_DAYS` days, in
  the `backups` volume. **The off-site copy** (the `offsite` part) sends each of them, encrypted to the
  platform team's public key, to a bucket elsewhere once `OFFSITE_*` are set in `.env` (`RUNBOOKS.md`, "The
  off-site copy"; "The server is lost" says how to come back on a new server).
- **Health**: the console's System health page; alerts by email to the platform team once mail is set up
  (`MAIL_*` in `.env`, then "Send a test email" on that page; `RUNBOOKS.md`, "Email"; `HEALTH_ALERT_TO` adds
  addresses); an uptime monitor can watch `/health` with `HEALTH_TOKEN` from `.env`.

- **Privacy pages**: `/privacy`, `/terms` and `/account-deletion` on the platform's address are the
  platform's (the links for the App Store and Google Play): set `PLATFORM_LEGAL_NAME` and
  `PRIVACY_CONTACT_EMAIL` in `.env`. Each academy's own (on its address) take its details from the back
  office ("Data requests" → "Privacy page"). The text needs a lawyer's review before launch.

- **The games designer** (console › Game studio): set `ANTHROPIC_API_KEY` (the platform's own Claude key)
  and `GAME_DESIGNER_MODEL` (the Claude model it designs with) in `.env`, then `docker compose up -d worker`.
  It works on its own queue beside the worker (a design takes a few minutes). Without them the studio says the
  designer is not set up; the games, their islands and publishing still work.

- **Malware scanning of uploads**: off on a new server (`UPLOAD_SCANNER=none`). Once the server can spare
  about 1.5 GB of memory, set `COMPOSE_PROFILES=scan` and `UPLOAD_SCANNER=clamav` in `.env` (`RUNBOOKS.md`,
  "Scanning uploads"). After that, every upload is checked by ClamAV, and refused while it does not answer
  (`UPLOAD_SCAN_REQUIRED`).
- **Other sites calling the API from a browser**: none are allowed. The portal calls the API from its
  server, and the apps are not browsers. A web page on another address that must call it goes in
  `CORS_ALLOWED_ORIGINS` (comma-separated).
- **Shipping**: on main, the deploy waits until every api and web check that ran on the commit has
  passed. The last one to finish ships it.

- **Going back**: run the deploy workflow for an older commit, or put its image in `IMAGE=` in `.env` and
  `docker compose up -d` (migrations already applied stay).
- **A new setting** the app needs on servers: add it, with a safe default or empty, to the settings
  `deploy.sh` writes on the first deploy (`settings()`), and to the table below. The next deploy adds it once,
  at the end of the `.env` of every server that lacks it; a value already there is never changed. A module's
  setting missing from both, without a default fit for a server, fails `tests/Unit/Deploy/ServerEnvTest.php`.
- **Not on yet**: live lessons through LiveKit (lessons use an outside link: `CLASSROOM_PROVIDER=external`)
  and recording them (LiveKit Egress, with `LIVEKIT_EGRESS_URL`, `RECORDINGS_DISK` and `RECORDINGS_RECORDER_ROOT`),
  email until an SMTP account is set (`MAIL_*`), WhatsApp, texts and push (messages go to the log), the off-site
  copy until a bucket and key are set (`OFFSITE_*`), real payment gateways (each academy adds its own
  keys), signing in with Google and Apple (the platform's clients: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`,
  `GOOGLE_APP_CLIENT_IDS`, `APPLE_SERVICES_ID`, `APPLE_APP_IDS`; each provider sends people back to
  `https://$DOMAIN/auth/social/{google|apple}/callback`), and academies' own domains.
- **Removing it all**: `docker compose down -v` here (the data goes too), delete this folder, and
  `sudo rm /etc/nginx/sites-enabled/academy-lms.conf /etc/nginx/sites-available/academy-lms.conf && sudo
  systemctl reload nginx`.

## Settings

The server's `.env` (in `~/academy-lms`, readable by the deploy user only) holds everything below. The
first deploy writes it with random secrets and safe defaults; each later deploy adds, with its default, any
setting this version reads that the file lacks, and says which. Change a value by hand, then
`docker compose up -d` (the parts read it when they start). Secrets live only here, never in the repository.

| Settings | What they do | Where the value comes from |
|---|---|---|
| `IMAGE`, `HTTP_PORT` | the version running, and the one port on 127.0.0.1 | `deploy.sh` |
| `APP_KEY`, `DB_*PASSWORD`, `REVERB_APP_KEY`/`SECRET`, `HEALTH_TOKEN`, `DEMO_PASSWORD`, `DEMO_PLATFORM_2FA_SECRET` | encryption key, database roles, live connections, `/health`, the demo's sign-in | random, from the first deploy; never change `APP_KEY` or the database passwords (gateway keys and two-step secrets are encrypted with it) |
| `PLATFORM_DOMAIN`, `PLATFORM_HOME_ACADEMY`, `PLATFORM_CONSOLE_DOMAIN`, `PLATFORM_CONSOLE_URL` | the platform's domain, an academy on the bare domain, the console's own address | you |
| `TRUSTED_PROXIES` | which addresses' forwarded headers count (the server's nginx, Docker's networks) | the default fits nginx on the same server |
| `CORS_ALLOWED_ORIGINS` | other web pages allowed to call the API from a browser | empty (none); comma-separated origins |
| `REVERB_ALLOWED_ORIGINS` | pages whose browsers may open a live connection | `*` (any): the apps send no origin and academies may have their own domains; private channels still need the API's signature. Narrow it (host patterns, e.g. `progma.store,*.progma.store`) only with no app and no academy on its own domain |
| `APP_MIN_VERSION`, `APP_ANDROID_URL`, `APP_IOS_URL` | the oldest app version let in (older ones are told to update) and the store pages that message links to | the release you require; Google Play and App Store pages |
| `QUEUE_WORKERS`, `WEB_PHP_THREADS` | queue workers in the worker part (notifications first; default 2), PHP threads in the web part (default 8) | raise `QUEUE_WORKERS` when System health shows the queue waiting, with `WORKER_MEMORY` (about 100 MB each) |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_*` (`MAIL_SCHEME=smtps` for port 465), `MAIL_TIMEOUT` | email: alerts, welcome links, codes, notifications | an SMTP account (the domain's mail, Amazon SES, Postmark, Brevo, ...); in the log until `MAIL_MAILER=smtp`. `MAIL_TIMEOUT` (default 15 seconds): how long a worker waits on a mail server that stops answering |
| `WHATSAPP_DRIVER`, `WHATSAPP_META_TOKEN`, `WHATSAPP_META_PHONE_NUMBER_ID`, `WHATSAPP_META_TEMPLATE`, `WHATSAPP_META_OTP_TEMPLATE` | WhatsApp messages and sign-in codes | the platform's WhatsApp Business account in Meta's Cloud API: a permanent token, the number's id, and the two templates approved under those names; `WHATSAPP_DRIVER=meta` |
| `SMS_DRIVER`, `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_FROM`, `TWILIO_MESSAGING_SERVICE_SID` | texts (codes, reminders) | the platform's Twilio account (a number or sender id, or a messaging service); `SMS_DRIVER=twilio` |
| `PUSH_DRIVER`, `FCM_PROJECT_ID`, `FCM_CREDENTIALS_FILE` | push to the apps | the apps' Firebase project: its id, and a service-account key (Firebase console › Project settings › Service accounts › Generate new private key) saved as `secrets/fcm.json` in this folder (or the path given here), then `PUSH_DRIVER=fcm`. Only the worker sees it, read-only (`/run/secrets/fcm.json`); `deploy.sh` leaves an empty stand-in until then and keeps it readable by the container's user. Never in git or the image; keep a copy with the platform team (the off-site copy does not send it) |
| `CLASSROOM_PROVIDER`, `LIVEKIT_URL`, `LIVEKIT_API_URL`, `LIVEKIT_API_KEY`, `LIVEKIT_API_SECRET` | live lessons: by each teacher's outside link, or LiveKit | a LiveKit Cloud project (or your own server): its wss address, its API address, and an API key and secret; then `CLASSROOM_PROVIDER=livekit` and its webhook pointed at `https://$DOMAIN/webhooks/livekit`. Blank, LiveKit is off and its webhooks refused |
| `LIVEKIT_EGRESS_URL`, `RECORDINGS_DISK`, `RECORDINGS_RECORDER_ROOT`, `AWS_*` | recording lessons (LiveKit Egress) and where the files go | a bucket for `RECORDINGS_DISK=s3` (needs the S3 adapter in the image first: `league/flysystem-aws-s3-v3`); recordings are not in the backups |
| `STRIPE_PLATFORM_SECRET`, `STRIPE_CONNECT_CLIENT_ID`, `STRIPE_CONNECT_WEBHOOK_SECRET`, `STRIPE_APPLICATION_FEE_BP` | academies connecting their own Stripe account to the platform's (Connect), and the platform's fee | the platform's Stripe account: secret key, Connect client id, the Connect webhook's signing secret; fee in basis points (0: none). Academies' own gateway keys are not here: each adds its own in the back office |
| `SOCIAL_REDIRECT_BASE`, `GOOGLE_*`, `APPLE_*` | signing in with Google and Apple | the platform's own OAuth clients (see "Not on yet" above) |
| `UPLOAD_SCANNER`, `UPLOAD_SCAN_REQUIRED`, `CLAMAV_HOST`, `COMPOSE_PROFILES` | malware scanning of uploads | `COMPOSE_PROFILES=scan` and `UPLOAD_SCANNER=clamav` once the server can spare 1.5 GB |
| `HEALTH_ALERT_TO`, `BACKUPS_EXPECTED`, `BACKUP_AT`, `BACKUP_KEEP_DAYS`, `OFFSITE_*` | alerts' extra addresses; the daily backup's time and retention; the off-site copy | you; the bucket and the platform team's age public key (`RUNBOOKS.md`) |
| `ANTHROPIC_API_KEY`, `GAME_DESIGNER_MODEL`, `PLATFORM_LEGAL_NAME`, `PRIVACY_CONTACT_EMAIL` | the games designer; the platform's privacy pages | the platform's Claude key and model; the legal name and the data contact |
| `WEB_MEMORY`, `WORKER_MEMORY`, ... `*_CPUS`, `REDIS_MAXMEMORY` (not written: compose.yml's defaults) | each part's memory and CPU limits | add to raise one |

## The files

| File | What it is |
|---|---|
| `../../apps/api/Dockerfile.prod` | the one production image (FrankenPHP, PHP 8.3, no dev packages, runs as `www-data`; a Node stage builds the portal, and the image carries its standalone server and Node) |
| `entrypoint.sh` | what each container runs: `web`, `portal`, `reverb`, `worker` (and the games designer's queue), `scheduler`, `relay`, `migrate`, `seed-demo` |
| `Caddyfile` | the web server inside the image: the app, Reverb under `/app/`, and the portal (`apps/web`) under `/ar`, `/en`, `/_next` and `/bff`; it trusts the forwarded headers of nginx (private addresses), so the portal knows a page is https |
| `php.ini` | production PHP settings (opcache, upload size) |
| `compose.yml` | the stack, with its limits and its one port |
| `postgres-init.sh` | the LMS roles on a new database (`apps/api/database/setup/roles.sql`) |
| `postgres-start.sh`, `pg_hba.conf` | how Postgres starts: every finished WAL segment copied into the backups volume, and who may connect (the backup part may take base backups) |
| `backup.sh` | the backup part: daily base backup, dump and files, retention, restore drills and restoring into a new volume |
| `offsite.sh` | the offsite part (on the app's image, with `age`, `rclone` and `psql`): each backup and WAL segment and the settings sent, encrypted, to a bucket elsewhere; retention there; fetching back with the private key |
| `restore.sh` | on the server: `list`, `now`, `check [TIME]`, `to TIME\|latest [offsite]`, `back VOLUME`, and from the off-site copy `offsite`, `check offsite KEYFILE`, `fetch KEYFILE [TIME]` |
| `RUNBOOKS.md` | what to do when something is wrong, and how to restore |
| `deploy.sh` | runs on the server: checks, first-run settings (and settings added since), migrations, start, health check, way back |
| `setup-nginx.sh`, `nginx/academy-lms.conf` | the server's nginx site, once |

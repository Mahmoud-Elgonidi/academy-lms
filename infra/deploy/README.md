# Deploying to a server

Every push to `main` that passes the API or web checks goes to the server on its own:
GitHub Actions builds the production image (the API, the panels and the teachers'
portal in one image), rehearses the deploy on its own machine, pushes the image to
GitHub's registry, then runs `deploy.sh` on the server over SSH
(`.github/workflows/deploy.yml`).

The LMS is built to share a server with other projects without touching them:

| What | How it stays apart |
|---|---|
| Files | one folder, `~/academy-lms`, owned by the deploy user (`.env` and `postgres.env` readable by it only) |
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
   this server's own password: `grep DEMO_PASSWORD ~/academy-lms/.env`. The teachers' portal is
   `https://demo.progma.store/ar` (or `https://progma.store/ar`); a browser opening the bare address goes there. The platform console is
   `https://progma.store/console` (`admin@platform.test`, same password); its authenticator secret is
   `DEMO_PLATFORM_2FA_SECRET` in `.env`, or print the current code:
   `cd ~/academy-lms && docker compose exec web php artisan platform:code admin@platform.test`.

Another web server instead of nginx needs the same as `nginx/academy-lms.conf`: pass `progma.store` and
`*.progma.store` to `127.0.0.1:<HTTP_PORT>` with `Host`, `X-Forwarded-For`, `X-Forwarded-Proto` and the
WebSocket upgrade headers.

## Day to day (in `~/academy-lms`)

```bash
docker compose ps                                  # the eight parts: web, portal, reverb, worker, scheduler, relay, postgres, redis
docker compose logs -f web                         # or portal, worker, scheduler, ...
docker compose exec web php artisan about
docker compose exec -T postgres pg_dump -U postgres -Fc lms > lms-$(date +%F).dump   # a backup
```

- **Going back**: run the deploy workflow for an older commit, or put its image in `IMAGE=` in `.env` and
  `docker compose up -d` (migrations already applied stay).
- **A new setting** the app needs on servers: add it to the `.env` that `deploy.sh` writes on the first
  deploy, and add it by hand to servers that already have a `.env` (it is never overwritten).
- **Not on yet**: live lessons through LiveKit (lessons use an outside link: `CLASSROOM_PROVIDER=external`),
  email, WhatsApp, texts and push (messages go to the log), real payment gateways (each academy adds its own
  keys), and academies' own domains.
- **Removing it all**: `docker compose down -v` here (the data goes too), delete this folder, and
  `sudo rm /etc/nginx/sites-enabled/academy-lms.conf /etc/nginx/sites-available/academy-lms.conf && sudo
  systemctl reload nginx`.

## The files

| File | What it is |
|---|---|
| `../../apps/api/Dockerfile.prod` | the one production image (FrankenPHP, PHP 8.3, no dev packages, runs as `www-data`; a Node stage builds the portal, and the image carries its standalone server and Node) |
| `entrypoint.sh` | what each container runs: `web`, `portal`, `reverb`, `worker`, `scheduler`, `relay`, `migrate`, `seed-demo` |
| `Caddyfile` | the web server inside the image: the app, Reverb under `/app/`, and the portal (`apps/web`) under `/ar`, `/en`, `/_next` and `/bff`; it trusts the forwarded headers of nginx (private addresses), so the portal knows a page is https |
| `php.ini` | production PHP settings (opcache, upload size) |
| `compose.yml` | the stack, with its limits and its one port |
| `postgres-init.sh` | the LMS roles on a new database (`apps/api/database/setup/roles.sql`) |
| `deploy.sh` | runs on the server: checks, first-run settings, migrations, start, health check, way back |
| `setup-nginx.sh`, `nginx/academy-lms.conf` | the server's nginx site, once |

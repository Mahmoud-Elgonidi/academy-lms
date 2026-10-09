# Runbooks

What to do when something on the server is wrong, or before it is. Every command runs in the deploy
folder on the server (`~/academy-lms`), as the user that deploys. Nothing here touches anything outside
the project's own containers and volumes.

**First, always:** the console's **System health** page (`/console/health`) says which part is wrong and
how long ago it last worked; the same in the terminal:

```bash
docker compose ps                                   # ten parts, all "running"
docker compose exec web php artisan platform:health # every check, with a hint for each fault
docker compose exec web php artisan platform:health --errors   # and the errors of the week: when, where, how often (redacted)
docker compose logs --tail=100 <part>               # web, portal, worker, scheduler, relay, reverb, backup, offsite, postgres, redis
```

Alerts reach the platform team (active console accounts, plus `HEALTH_ALERT_TO` in `.env`) by email when
a check fails in two checks in a row, five minutes apart (so a restart does not wake anyone), every six
hours while it fails, and when it is well again. They need mail set up (see **Email** below); until
then they are in `docker compose logs scheduler`, and the health page's "Email" check says so.
An uptime monitor can watch `GET https://<domain>/health` with `Authorization: Bearer <HEALTH_TOKEN>`:
200 while nothing fails, 503 when something does, and only the checks' statuses in the answer. A stack
that has just started says the scheduler and the worker fail until their first beat, within a minute or two;
let the monitor confirm a fault before it calls anyone.

## Backups

The `backup` part keeps, in the `backups` volume:

| What | When | Kept |
|---|---|---|
| Every change to the database (the WAL, compressed) | as it happens, at least every 5 minutes | from the oldest kept base backup on |
| A base backup (the whole database) | every day at `BACKUP_AT` (UTC, default 05:30), and 10 minutes after a fresh start | `BACKUP_KEEP_DAYS` days (default 7), the latest two always |
| A dump of the database and its roles (`pg_dump`) | with each base backup | the same |
| The app's files (proofs, homework, materials, tickets; recordings left out: `BACKUP_FILES_SKIP`) | with each base backup | the same |

So the database can go back to **any moment** in the last `BACKUP_KEEP_DAYS` days, losing five minutes
at most. Each run, and each drill, is listed on the System health page.

```bash
./restore.sh list    # what is kept, and its size
./restore.sh now     # a backup now (before a risky change, say)
```

**They are on the same server as the database.** A disk that dies, or a server that is lost, takes them
too: set up **the off-site copy** (next), which sends each of them elsewhere as it appears.

## The off-site copy

The `offsite` part sends everything the backup part keeps to a bucket on another provider (any
S3-compatible storage: Cloudflare R2, Backblaze B2, Wasabi, AWS S3, a MinIO of your own), encrypted
on the way with [age](https://age-encryption.org) to a **public key**. The server can write the copies
but cannot read them; the **private key** that reads them stays with the platform team, never on the
server. What goes:

| What | When | Kept in the bucket |
|---|---|---|
| Every change (each WAL segment) | within 5 minutes of being archived (`OFFSITE_EVERY`) | from the oldest kept base backup on |
| Each base backup, dump with its roles, copy of the files | as soon as the backup part makes it | `OFFSITE_KEEP_DAYS` days (default 30), the latest two always |
| The server's settings (`.env`, `postgres.env`) | whenever they change | the same |

The settings go too because a restore needs them: `APP_KEY` decrypts the gateways' keys and the
authenticator secrets, and the restored database has the passwords written there. Nothing in the bucket
is ever written over. So the database can come back even when the server does not, losing about ten
minutes at most.

**Set it up once:**

1. **The key**, on your own computer (`age-keygen` from your package manager, or
   [the releases](https://github.com/age-encryption/age/releases)):
   ```bash
   age-keygen -o academy-lms-offsite.key     # prints "Public key: age1..."
   ```
   Keep `academy-lms-offsite.key` in the team's password manager **and** somewhere offline (a USB stick
   in a drawer). Without it the copies cannot be read: there is no other way in. Two people can each
   have their own key: put both public keys, comma-separated.
   (No age at hand? `docker compose run --rm --no-deps --entrypoint age-keygen offsite` prints a new
   key on the server's screen, kept nowhere: copy it away, then clear the terminal.)
2. **The bucket**: a new, private bucket, and an access key for it alone. Better still, with object
   versioning or an object lock of 30 days, so that someone who takes the server cannot delete the
   copies: then give the key no right to delete, set `OFFSITE_KEEP_DAYS=0`, and let the bucket's own
   lifecycle rule remove what is older than 30 days.
3. **The settings**, in `.env` on the server:
   ```bash
   OFFSITE_S3_ENDPOINT=https://<account>.r2.cloudflarestorage.com   # empty for AWS S3
   OFFSITE_S3_REGION=auto                                            # R2: auto; AWS: the bucket's region
   OFFSITE_S3_BUCKET=academy-lms-backups
   OFFSITE_S3_PREFIX=academy-lms
   OFFSITE_S3_ACCESS_KEY=...
   OFFSITE_S3_SECRET_KEY=...
   OFFSITE_AGE_RECIPIENT=age1...                                     # the PUBLIC key only
   OFFSITE_KEEP_DAYS=30
   ```
   then `docker compose up -d`. Within minutes `docker compose logs offsite` says what it sent, and the
   health page's "Off-site copy" turns OK.

**Day to day:**

```bash
./restore.sh offsite                                     # what is in the bucket
./restore.sh check offsite ~/academy-lms-offsite.key      # a restore drill from the bucket alone
```

The drill fetches the latest base backup and its changes from the bucket, decrypts them with the key,
brings the database up apart from everything, checks it and throws it away; it is recorded like the
other drills (`off-site: latest`). Do it with the quarterly drill. Copy the key to the server only for
that (`chmod 600`), and delete it right after: `shred -u ~/academy-lms-offsite.key`.

If the health page says the off-site copy is not sending: `docker compose logs --tail=50 offsite`.
A wrong key or bucket name is said there in plain words; a bucket that does not answer is retried
every five minutes, and what waited goes as soon as it answers.

## Restore drill (every three months at least)

A restore that was never tried is a hope. The drill brings the database up, as it was at a moment,
next to the live one (nothing live is touched), checks it opens and holds the academies, people,
lessons and invoices, checks the latest dump reads back, and records the result:

```bash
./restore.sh check                        # as late as the backups go
./restore.sh check "2026-10-09 10:15"     # as it was then (UTC)
```

`drill ok: restored to …` is what you want. The System health page warns when the last good drill is
more than three months old.

## Putting the database back to a moment

When data was lost or damaged (a wrong import, a mistaken delete), and only then:

```bash
./restore.sh to "2026-10-09 10:15"   # UTC; just before the mistake. Or: ./restore.sh to latest
```

It asks you to type `academy-lms`, then:

1. brings the database as it was at that moment into a **new volume** (`academy-lms_pgdata-<stamp>`);
2. stops the app and the database, points the database at the new volume (`PG_VOLUME` in `.env`),
   starts them, runs the migrations, and takes a backup at once;
3. keeps the database as it was before, untouched, in its own volume.

Everything after that moment is no longer in use. To go back to the database as it was before the
restore: `./restore.sh back academy-lms_postgres-data` (or the volume it names). Once you are sure,
an old volume can go: `docker volume rm <volume>`.

**Files are not put back with the database.** If files were lost too, take them from the backup made
closest to (and after) the moment:

```bash
./restore.sh list                                   # find files/storage-<stamp>.tar.gz
docker compose run --rm --no-deps -v academy-lms_app-storage:/files --entrypoint tar backup \
    -xzf /backups/files/storage-<stamp>.tar.gz -C /files
```

## The server is lost

The disk died, the provider closed the account, or the server cannot be trusted any more. With the
off-site copy and its private key, on a **new** server:

1. Deploy as for the first time (README.md: "The server, once", then the deploy workflow, or
   `deploy.sh` by hand) **without** demo data. The stack comes up empty, with new secrets.
2. Put the off-site settings into the new `.env` (the same bucket, prefix and keys as before), and copy
   the private key over (`chmod 600 ~/academy-lms-offsite.key`). Do not run `docker compose up` yet.
3. Fetch the backups and the settings of then:
   ```bash
   ./restore.sh fetch ~/academy-lms-offsite.key latest      # or a moment: "2026-10-09 10:15" (UTC)
   ```
   It brings the newest base backup made before that moment, the changes after it, its dump and
   files, into the volume `academy-lms_offsite-restore`, and writes the old server's settings next to
   `restore.sh` as `.env.offsite` and `postgres.env.offsite` (with this server's `IMAGE`, `HTTP_PORT`
   and `PG_VOLUME`).
4. Take the old settings, so the restored database's passwords and the encrypted keys match:
   ```bash
   mv .env.offsite .env && mv postgres.env.offsite postgres.env
   ```
5. Put the database back, from what was fetched (it asks you to type `academy-lms`):
   ```bash
   ./restore.sh to latest offsite                           # the same moment as in step 3
   ```
6. The files, from the fetched copy:
   ```bash
   docker run --rm -v academy-lms_offsite-restore:/fetched:ro -v academy-lms_app-storage:/files \
       postgres:17 sh -c 'tar -xzf /fetched/files/storage-*.tar.gz -C /files'
   ```
7. Point the domain at the new server (DNS, then `setup-nginx.sh` and the certificate as in README.md),
   check `/console/health`, then remove what was fetched and the key:
   `docker volume rm academy-lms_offsite-restore && shred -u ~/academy-lms-offsite.key`.

## Email

Alerts to the platform team, welcome and password links, and sign-in codes by email go through the
server's mail settings. Until they are set, `MAIL_MAILER=log` writes every message to the log and
nobody receives it; the health page's "Email" check warns about it on the server.

1. An SMTP account that may send from the domain: the domain's own mail, or a sending service (Amazon
   SES, Postmark, Brevo, Mailgun, ...). Add its SPF and DKIM records to the domain's DNS, as the
   service says, or the messages land in spam.
2. In `.env`:
   ```bash
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.example.com
   MAIL_PORT=587                      # STARTTLS; for 465 add MAIL_SCHEME=smtps
   MAIL_USERNAME=...
   MAIL_PASSWORD=...
   MAIL_FROM_ADDRESS=no-reply@<domain>
   ```
   then `docker compose up -d`.
3. Send a test: the **Send a test email** button on the console's System health page (to you), or
   ```bash
   docker compose exec web php artisan platform:mail-test                 # to the platform team
   docker compose exec web php artisan platform:mail-test you@example.com
   ```

When a message cannot leave (a wrong password, a server that does not answer), the "Email" check turns
red with the reason, `GET /health` answers 503 (so the uptime monitor calls), and alerts are tried
again at the next check.

## The database's change archive fails

The health page says "Change archive (WAL)" is down: Postgres cannot copy finished changes into the
backups volume, and **they pile up inside the database's own volume until its disk fills**.

```bash
docker compose logs --tail=50 postgres     # "archive command failed" and why
docker compose exec postgres df -h /backups /var/lib/postgresql/data
```

Usually the disk is full (see below). Once there is room, Postgres archives what waited by itself.

## Disk space is running out

```bash
df -h; docker system df                     # the server's disk, and Docker's share of it
./restore.sh list                           # the backups' share
```

Lower `BACKUP_KEEP_DAYS` in `.env` and `docker compose up -d backup` (the next backup prunes), ask the
academies to shorten how long they keep recordings, or add disk. Never delete files in the backups'
`wal/` folder by hand: the backup part removes what no kept base backup needs.

## Scheduled tasks stopped (scheduler)

Lessons are not generated, reminders and the attendance sweep do not run, backups and alerts stop.

```bash
docker compose logs --tail=100 scheduler
docker compose restart scheduler
```

## Messages are not going out (worker or queue)

```bash
docker compose logs --tail=100 worker
docker compose restart worker
docker compose exec web php artisan queue:failed        # what failed and why
docker compose exec web php artisan queue:retry all     # once the cause is fixed
```

## Screens do not update by themselves (relay or Reverb)

Open screens fall back to asking every 30 seconds, so nothing is lost; they are just slower.

```bash
docker compose logs --tail=100 relay reverb
docker compose restart relay reverb
```

## A payment gateway is down

Payments already made are not lost: the gateway's webhooks are retried by the gateway, and unfinished
online checkouts are checked again every five minutes (`billing:sweep`) until the gateway answers.
While it is down, the academy can take transfers: on the invoice, the payer sends a proof
("I paid by transfer") and the academy approves it. Nothing in the app needs changing.

## Live lessons through LiveKit are down

An academy can switch its lessons to an outside link (Zoom, Meet) at once: back office →
Settings → Live classroom → "An outside link per teacher" (each teacher's own meeting link). New lesson rooms use
the new provider; a room already open keeps the one it was made with. Switch back the same way.

## A government changed its clock rules (tzdata)

The rules come with the image (PHP's and Postgres' time zone data). Rebuild and deploy the image
(Dependabot proposes base image updates monthly), then re-plan the clock-change notices:

```bash
docker compose exec web php artisan scheduling:dst
```

## Changing a secret

Edit the value in `.env` (never in the repository, never in a log), then `docker compose up -d`.
Do not change `APP_KEY` on a live server: it decrypts the gateways' keys, the authenticator secrets and
the sessions. `HEALTH_TOKEN` can change any time (and the uptime monitor with it).

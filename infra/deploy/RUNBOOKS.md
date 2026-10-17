# Runbooks

What to do when something on the server is wrong, or before it is. Every command runs in the deploy
folder on the server (`~/academy-lms`), as the user that deploys. Nothing here touches anything outside
the project's own containers and volumes.

**First, always:** the console's **System health** page (`/console/health`) says which part is wrong and
how long ago it last worked; the same in the terminal:

```bash
docker compose ps                                   # ten parts (eleven with scanning), all "running"
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

If they go out, only late (System health shows the queue waiting; reminders should arrive ten minutes
before a lesson): more workers take it at once. `QUEUE_WORKERS=3` (or 4) in `.env`, `WORKER_MEMORY` about
100 MB more for each, then `docker compose up -d worker`.

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

## AI helpers

The academies' helpers (practice items, report drafts from a teacher's voice note, recitation checks,
the weekly message's wording) call Claude with the platform's `ANTHROPIC_API_KEY`, and the ones that
listen send the audio to ElevenLabs (`ELEVENLABS_API_KEY`) for its words. Each academy turns each helper
on in Settings › AI helpers (all off at first, only on a plan with the AI area); what each sends is written
on that page. A helper's result is always a draft or a suggestion a person checks: when one is off or
down, the work goes on by hand and the weekly message goes out with the facts alone.

System health's **AI helpers** check: *off* with no key, a warning when calls to one provider failed
three times in a row (the hint names it), and a fault when the `assist` queue's beat stops.

```bash
docker compose logs --tail=100 worker | grep -i assist     # the assist queue's worker
docker compose exec web php artisan queue:failed           # a helper's job that gave up, and why
```

- **Turn them all off at once** (a provider's outage, a bill to stop): `AI_DRIVER=off` in `.env`, then
  `docker compose up -d web worker`. Every helper says it is off; nothing else changes. Back with `claude`.
- **People are told to try again** ("busy"): more of them answer at once with `AI_CONCURRENT` (default 3;
  each holds a web thread while it waits, so keep it well under `WEB_PHP_THREADS`).
- **An academy hit its day's cap** (`AI_DAILY_CAP`, calls a day): it comes back at midnight UTC; raise the
  cap for all if it is too low.
- **A new key**: in the console's Platform settings (see **Changing a secret**); "Try it" checks both
  providers' keys and that the models named exist.
  Each call's model, tokens, seconds of audio and outcome are kept in `ai_usage` (never what was said or
  sent, never who), which the academy's settings page counts for the month.
- **Before an academy turns on the recitation check**: measure it on real recordings whose mistakes a
  teacher marked by ear. Put the recordings in a folder with a `cases.json`
  (`[{"file": "01.webm", "surah": 78, "from": 1, "to": 5, "mistakes": [{"verse": 3, "word": 2, "kind": "missing"}]}]`,
  a word counted from 1 in its verse), copy it into the container, and run it; it keeps nothing and
  involves no academy, but the recordings go to the speech provider like any check. `--heard` also prints
  what was heard (on the screen only).

  ```bash
  docker compose cp ./recitations web:/tmp/recitations
  docker compose exec web php artisan learning:try-recitation /tmp/recitations
  docker compose exec web rm -r /tmp/recitations
  ```

## The Quran's text

The Quran's words (lesson passages, practice suggestions) come only from Tanzil's Simple text and its
metadata, kept in the storage volume (`storage/app/platform/quran`, with `manifest.json`: their SHA-256 and
when they came; in the daily backup with the rest of the files). The deploy fetches them the first time
(`curriculum:quran --fetch --if-missing`); a deploy that could not reach Tanzil says so and goes on.

```bash
docker compose exec web php artisan curriculum:quran            # which copy, its version and checksums
docker compose exec web php artisan curriculum:quran --fetch    # fetch again (or the console: Platform settings › "Fetch again")
```

A fetch is kept only once both files pass every check (114 surahs, 6,236 verses in order, Arabic lines,
no rub-el-hizb or sajdah signs, Tanzil's notice); otherwise it says what failed and the copy in use stays.
Tanzil's addresses are `QURAN_TEXT_URL` and `QURAN_DATA_URL` in `.env` should they move. Until a copy is
there, lessons cannot name a passage of the Quran and everything else works.

A server with the demo academy brings it up to date on every deploy, whatever `DEMO_DATA` says
(`DemoCatchUpSeeder`, its line in the deploy's output gives the counts): once the text is here, the demo's
Juz' Amma lessons read their surahs, its Quran learners' last lessons have a few marked words, and their
hifz maps are made from the reports already handed in.

## Scanning uploads

Every file people upload (homework, payment receipts, ticket attachments, lesson materials, import
files, logos) is checked for malware before it is kept, once the server runs ClamAV. It is off on a new
server because clamd keeps its signatures in memory: it needs about 1.5 GB to spare. To turn it on:

```bash
# in .env
COMPOSE_PROFILES=scan
UPLOAD_SCANNER=clamav
docker compose up -d clamav            # the first start downloads the signatures: a few minutes
docker compose logs clamav | grep -i "clamd started"
docker compose up -d web worker        # read the new .env
docker compose exec web php artisan platform:health   # "Scanning uploads": ok, with the signatures' date
```

The stack then has eleven parts. freshclam in the same container updates the signatures by itself; the
health page shows their date, and the files refused this week.

- **An upload was refused as unsafe:** the person sees "This file did not pass the safety check" and
  nothing was kept. The log has a warning with the signature's name (not the file). If it is a false
  alarm, ask for the file in another format (a PDF printed again, an audio file saved again).
- **"Scanning uploads" fails (clamd does not answer):** uploads are refused while it is down, so no
  unchecked file gets in. `docker compose ps clamav`, `docker compose logs --tail=100 clamav`, then
  `docker compose restart clamav`. Running out of memory shows as a restart loop: raise `CLAMAV_MEMORY`
  in `.env`, or free memory on the server. To keep uploads open while you fix it, set
  `UPLOAD_SCAN_REQUIRED=false` and `docker compose up -d web worker`. Files then go in unchecked until
  you set it back.
- **To turn it off:** `UPLOAD_SCANNER=none`, `COMPOSE_PROFILES=`, `docker compose up -d web worker`,
  `docker compose stop clamav`.

## A government changed its clock rules (tzdata)

The rules come with the images. PHP's are the `timezonedb` extension, the latest at the image's
build: rebuild and deploy the image (the deploy's rehearsal refuses one whose PHP still has Morocco's
old Ramadan hour). Postgres reads Debian's data in `postgres:17`, which a deploy does not pull: in a
quiet hour, `docker compose pull postgres && docker compose up -d postgres`. Then re-plan the
clock-change notices:

```bash
docker compose exec web php artisan scheduling:dst
```

## A personal data breach

When personal data may have leaked (a lost laptop with a copy, a key in a log, someone reading another
academy's records, a backup bucket left open), the clock starts when anyone on the team learns of it:
the law asks for the authority within **72 hours** (Saudi Arabia: SDAIA, under the PDPL; the EU: each
academy's supervisory authority, through the academy as controller), and the people at risk without delay.

1. **Stop it**: change the secret that leaked (**Changing a secret** below), suspend the account or the
   academy from the console, take a public bucket private. Note the time of each step.
2. **Keep the evidence**: the audit log (back office → Activity, console → Audit), `platform:health --errors`,
   `docker compose logs` of the parts involved, before they rotate. Do not delete anything.
3. **Work out what and whom**: which academies, which people, which kinds of data (contact details, a
   child's data, recordings, invoices). The academy is the controller: tell its owner at once, in writing.
4. **Report** within 72 hours, with what is known so far (it can be completed later): what happened, when,
   the data and roughly how many people, the likely effect, what was done and what will be. The academy
   reports for its people; the platform reports for its own team and helps each academy with the facts.
5. **Tell the people** when it puts them at risk: plainly, in their language, what happened and what they
   can do (change a password, watch for messages pretending to be the academy).
6. **Afterwards**: write down the cause and the fix, and add a test or a check so it cannot happen again.

## A person's data requests are not moving

Copies are built by the worker (**Messages are not going out** above); approved erasures and week-old
copies are handled every quarter of an hour by the scheduler (`privacy:sweep`). To run it by hand:

```bash
docker compose exec web php artisan privacy:sweep
```

An erasure the academy approved is done a day later. If something came up meanwhile (a new lesson, an
invoice), it goes back to the academy's "Data requests" page with the reason, and nothing is erased.

## Changing a secret

**An outside service's key** (Anthropic, ElevenLabs, SMTP, WhatsApp, Twilio, Firebase's file, LiveKit,
Stripe Connect, Google and Apple): in the console, **Platform settings** (an owner of the platform team).
Someone on the console's team who does not see it is not an owner yet: on GitHub, **Actions › console-owner
› Run workflow** with their email (it runs `platform:admin <email> --owner` on the server, audited), or here,
as the deploy user in `~/academy-lms`: `printf '%s\n' someone@example.com | bash console.sh owner`.
Someone not on the team yet ("nobody on the platform team has this email"): put a password of 12
characters or more in the repository secret `CONSOLE_ADMIN_PASSWORD`, then run **console-owner** with their
email and their name; they are added as an owner (`platform:admin <email> <name> --owner --password-stdin`:
the password reaches the command on stdin, never on a command line or in the log), sign in at `/console`
with it and set up the authenticator app. Change or delete the secret afterwards; the console keeps the
password it was given.
Type the new one over the hidden one and save: it is in use from the next request, job or command, with no
restart, and the audit log names the setting, never the value. "Try it" on its section shows the service
took it. To go back to the server's own value, tick "Remove the saved …" (a key) or empty the field.

Anything else: edit the value in `.env` (never in the repository, never in a log), then `docker compose up -d`.
Do not change `APP_KEY` on a live server: it decrypts the gateways' keys, the authenticator secrets and
the sessions. `HEALTH_TOKEN` can change any time (and the uptime monitor with it).

**The demo password and the demo console key** (say they were shown to someone): `DEMO_PASSWORD` and
`DEMO_PLATFORM_2FA_SECRET` are only read when the demo is loaded, so new values in `.env` need one more
step to reach the accounts. As the deploy user, in `~/academy-lms`:

```bash
sed -i "s/^DEMO_PASSWORD=.*/DEMO_PASSWORD=$(head -c 30 /dev/urandom | base64 | tr '+/' 'Kq' | tr -d '\n=' | cut -c1-16)/" .env
sed -i "s/^DEMO_PLATFORM_2FA_SECRET=.*/DEMO_PLATFORM_2FA_SECRET=$(head -c 20 /dev/urandom | base32 | tr -d '=\n')/" .env
docker compose up -d
docker compose exec web php artisan identity:demo-secrets
```

Every demo account (`…@demo.test`, in each active academy) and the demo console team (`…@platform.test`)
take the new values, their sign-ins end (apps and panels ask again), and each academy's activity log and
the console's say so; an address on `.test` is never a real person's, so no real account changes. The
console admin's authenticator app needs the new key (`grep DEMO_PLATFORM_2FA_SECRET .env`, or
`platform:code admin@platform.test` for one code); an owner, admin or accountant keeps the authenticator
they set up themselves.

**A real platform team**: `docker compose exec web php artisan platform:admin you@company.com "Your name"`
adds one (it asks for the password and sets up the authenticator at the first sign-in to `/console`). Only
owners see Platform settings: the team's first admin is one, `platform:admin you@company.com --owner`
makes another and `--no-owner` takes it back (audited; never the last owner who can sign in, so make the
real one an owner before locking the demo account).
Before real academies arrive, lock the demo console account once a real one can sign in:
`php artisan platform:admin admin@platform.test --deactivate` (audited; its sessions end, and so does a
sign-in as someone it started; never the last admin who can sign in). `--reactivate` lets it back in.
Someone who leaves the team is locked the same way.

**The console on its own host**: set `PLATFORM_CONSOLE_DOMAIN=console.<domain>` and
`PLATFORM_CONSOLE_URL=https://console.<domain>/console` in `.env`, add the name to the certificate, then
`docker compose up -d`: the console and its sign-in answer there alone, and 404 on every academy's host.

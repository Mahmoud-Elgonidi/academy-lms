# Runbooks

What to do when something on the server is wrong, or before it is. Every command runs in the deploy
folder on the server (`~/academy-lms`), as the user that deploys. Nothing here touches anything outside
the project's own containers and volumes.

**First, always:** the console's **System health** page (`/console/health`) says which part is wrong and
how long ago it last worked; the same in the terminal:

```bash
docker compose ps                                   # nine parts, all "running"
docker compose exec web php artisan platform:health # every check, with a hint for each fault
docker compose logs --tail=100 <part>               # web, portal, worker, scheduler, relay, reverb, backup, postgres, redis
```

Alerts reach the platform team (active console accounts, plus `HEALTH_ALERT_TO` in `.env`) by email when
a check starts failing, every six hours while it fails, and when it is well again. They need mail set up
(`MAIL_MAILER` and its settings in `.env`); until then they are in `docker compose logs scheduler`.
An uptime monitor can watch `GET https://<domain>/health` with `Authorization: Bearer <HEALTH_TOKEN>`:
200 while nothing fails, 503 when something does, and only the checks' statuses in the answer.

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
too. Copy them elsewhere regularly, e.g. every night from another machine, or by hand:

```bash
docker compose cp backup:/backups ./backups-$(date +%F)   # then move that folder off the server
```

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

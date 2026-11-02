import { execFileSync } from 'node:child_process';
import { closeSync, fstatSync, openSync, readFileSync, readSync } from 'node:fs';
import { resolve } from 'node:path';
import type { Page } from '@playwright/test';

/**
 * The API's side of the end-to-end tests: artisan in apps/api (E2E_API_DIR),
 * with the environment the API runs with (the same DB_DATABASE and the
 * rest); E2E_ARTISAN replaces `php artisan` (say, `docker compose exec -T api
 * php artisan`).
 */
export function artisan(args: string[]): string {
  const [command = 'php', ...prefix] = (process.env.E2E_ARTISAN ?? 'php artisan').split(' ').filter(Boolean);
  try {
    return execFileSync(command, [...prefix, ...args], {
      cwd: resolve(process.env.E2E_API_DIR ?? '../api'),
      env: process.env,
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'pipe'],
      timeout: 120_000,
    });
  } catch (error) {
    const { stdout = '', stderr = '' } = error as { stdout?: string; stderr?: string };
    throw new Error(`artisan ${args.join(' ')} failed:\n${stdout}\n${stderr}`.trim());
  }
}

export const FIXTURES_FILE = 'e2e/.auth/fixtures.json';

/** What E2eResetSeeder set up for this run. */
export type Fixtures = {
  /** Ziad's homework: one to hand in in words, one handed in as a recording for Khaled to mark. */
  homework: { hand_in: string; to_mark: string };
  /** The demo academy's own host ({slug}.{platform domain}, with the API's port here). */
  academy_host: string;
  /**
   * The demo group (docs/architecture/02, section 25: Mona's children's
   * Arabic group, running): one of its lessons ahead for a family to say they
   * will not attend, the report of one held for Mona to write for the whole
   * class, and who signs in (a guardian of a child in it, a student in it in
   * the kids' look, and its teacher).
   */
  group: {
    id: string;
    /** A lesson of the group ahead, in good time to excuse (the guardian's child seated, not yet excused). */
    lesson_id: string;
    /** A report of a held lesson of the group, due (any student's: the form is the class's). */
    report_id: string;
    /** Sign-ins (the demo password): a guardian of a child in the group, a student in it, and its teacher. */
    guardian: string;
    student: string;
    teacher: string;
  };
  /**
   * The demo's Quran (docs/architecture/02, section 26; DemoQuranSeeder and
   * E2eResetSeeder): a Quran course with a plan, its teacher's lesson whose
   * room is open or held today and not yet recorded, the course's family
   * (a guardian in the classic look and the student in the kids' look), and
   * a standing certificate's verification code (the academy shows
   * certificates to families).
   */
  quran: {
    /** Sign-ins (the demo password): the course's teacher, a guardian of its student, and the student. */
    teacher: string;
    guardian: string;
    student: string;
    /** A lesson of the course with today's portion, not yet recorded. */
    lesson_id: string;
    enrollment_id: string;
    /** A standing certificate of the student (XXXX-XXXX-XXXX-XXXX) and its number. */
    certificate_code: string;
    certificate_number: string;
  };
};

export function fixtures(): Fixtures {
  return JSON.parse(readFileSync(FIXTURES_FILE, 'utf8')) as Fixtures;
}

/** Where the API answers this machine (the panels, Laravel's public pages). */
export const API = process.env.E2E_API_URL ?? 'http://127.0.0.1:8000';

/** A request to the API for this path as the academy's own host would make it (its host may have no DNS here). */
export function onAcademyHost(path: string): [string, { headers: Record<string, string> }] {
  return [`${API}${path}`, { headers: { Host: fixtures().academy_host } }];
}

/**
 * The academy's own host in this page's browser, as the server's Caddy serves
 * it (its name reaches this machine through /etc/hosts, as in CI, for the
 * live socket on Reverb's port; this route stands in for Caddy on the API's port): the portal's paths (/ar, /en, /_next, /bff)
 * to the portal, everything else (the gateway's pretend page, the way back
 * from it) to the API with that Host. The request's own headers (cookies,
 * X-Forwarded-For, the portal's session) go along. A redirect within that
 * host comes to the browser as an immediate refresh to the same address, with
 * its cookies: a browser follows a fulfilled redirect without asking this
 * route again.
 */
export async function serveAcademyHost(page: Page, portal: string): Promise<string> {
  const host = fixtures().academy_host;
  const origin = `http://${host}`;
  // The portal's session goes along, as it is on the academy's host on a server.
  const session = await page.context().cookies(portal);
  await page.context().addCookies(session.map(({ name, value, expires, httpOnly, secure, sameSite }) => ({ name, value, expires, httpOnly, secure, sameSite, url: origin })));
  await page.route(`${origin}/**`, async (route) => {
    const url = new URL(route.request().url());
    const toPortal = /^\/(ar|en|_next|bff)(\/|$)/.test(url.pathname);
    const headers = { ...(await route.request().allHeaders()), host };
    const response = await route.fetch({ url: `${toPortal ? portal : API}${url.pathname}${url.search}`, headers, maxRedirects: 0 });
    const location = response.headers()['location'];
    const next = location === undefined ? null : new URL(location, origin);
    if (response.status() >= 300 && response.status() < 400 && next?.origin === origin) {
      const kept = Object.fromEntries(Object.entries(response.headers()).filter(([name]) => !['location', 'content-length', 'content-type', 'content-encoding'].includes(name)));
      const to = next.href.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
      await route.fulfill({ status: 200, headers: kept, contentType: 'text/html', body: `<!doctype html><meta http-equiv="refresh" content="0;url=${to}">` });
      return;
    }
    await route.fulfill({ response });
  });
  // The academy's logo is at the API's APP_URL, which a page here does not reach (a failed load, tolerated): from a
  // page on this host Chromium would refuse it outright as a loopback address, so it fails the same way here.
  await page.route(
    (url) => url.host !== host && url.pathname.startsWith('/storage/'),
    async (route) => (route.request().frame().url().startsWith(`${origin}/`) ? route.abort('connectionrefused') : route.fallback()),
  );
  return host;
}

/** A lesson whose room is open now (for a quarter of an hour at least), on its teacher's outside link: Khaled's or Ziad's, found or booked as the test goes in. */
export function lessonNow(who: 'teacher' | 'student'): string {
  const id = artisan(['tinker', `--execute=echo Database\\Seeders\\E2eResetSeeder::lessonNow('${who}');`]).trim().split('\n').pop() ?? '';
  if (!/^[0-9a-f-]{36}$/.test(id)) throw new Error(`No lesson open now for the ${who}: ${id}`);
  return id;
}

/** Staff approve the receipt Ziad's guardian sent with this reference, as the back office would (ApprovePaymentProof); the invoice's number. */
export function approveProof(reference: string): string {
  if (!/^[\w-]+$/.test(reference)) throw new Error(`Not a reference: ${reference}`);
  const out = artisan(['tinker', `--execute=echo Database\\Seeders\\E2eResetSeeder::approveProof('${reference}');`]);
  return out.trim().split('\n').pop() ?? '';
}

/* ── A family's own free trial (the academy's page, its links) ── */

/** Who books the demo's free trials: `staff` (a request), or `family` (its own time; the global setup puts it back). */
export function trialBooking(mode: 'family' | 'staff'): void {
  const out = artisan(['tinker', `--execute=echo Database\\Seeders\\E2eResetSeeder::trialBooking('${mode}');`]).trim().split('\n').pop();
  if (out !== mode) throw new Error(`Free trials not set to ${mode}: ${out}`);
}

/** Someone else takes a free trial's time (UTC) meanwhile: every teacher free then is held for ten minutes. */
export function takeTrialSlot(utc: string): number {
  if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(utc)) throw new Error(`Not a UTC instant: ${utc}`);
  return Number(artisan(['tinker', `--execute=echo Database\\Seeders\\E2eResetSeeder::takeTrialSlot('${utc}');`]).trim().split('\n').pop());
}

/** The paths the family's messages carry for the trial asked for with this number: pick (a time), and change and cancel once booked. */
export function trialLinks(phone: string, locale: 'ar' | 'en' = 'ar'): { pick: string; change?: string; cancel?: string } {
  if (!/^\+\d{8,15}$/.test(phone)) throw new Error(`Not a number: ${phone}`);
  const out = artisan(['tinker', `--execute=echo Database\\Seeders\\E2eResetSeeder::trialLinks('${phone}', '${locale}');`]);
  return JSON.parse(out.trim().split('\n').pop() ?? '{}');
}

/**
 * The code just sent to a number, as WhatsApp's log driver wrote it to the
 * API's log (WHATSAPP_DRIVER=log, a laptop or CI only; the log masks the
 * number to its last two digits, so each test uses a number of its own).
 * The code leaves after the API answers, so this waits a little for it.
 */
export async function phoneCode(phone: string): Promise<string> {
  const log = resolve(process.env.E2E_API_DIR ?? '../api', 'storage/logs/laravel.log');
  const ends = [`code to ${phone} `, `code to ***${phone.slice(-2)} `];
  for (let tries = 0; tries < 40; tries++) {
    const fd = openSync(log, 'r');
    const size = fstatSync(fd).size;
    const length = Math.min(size, 200_000);
    const tail = Buffer.alloc(length);
    readSync(fd, tail, 0, length, size - length);
    closeSync(fd);
    const lines = tail.toString('utf8').split('\n').filter((line) => ends.some((end) => line.includes(end)));
    const found = lines.map((line) => /: (\d{6})\b/.exec(line)?.[1]).filter(Boolean);
    if (found.length > 0) return found.at(-1)!;
    await new Promise((done) => setTimeout(done, 250));
  }
  throw new Error(`No code in the API's log for ${phone} (WHATSAPP_DRIVER=log?)`);
}

/* ── After the free trial (the report, the offer, the next trial) ── */

/** A family new to this run after its free trial (E2eResetSeeder::trialAfter): its links' paths, the report's lesson, and its sign-ins. */
export type TrialAfter = {
  /** The report's link (Arabic, English): an assessed Tajweed trial two days ago, an open offer, a second trial to book. */
  report: string;
  report_en: string;
  /** Another family's "book again" link after a Quran trial its child missed yesterday. */
  rebook: string;
  /** The report's trial (its lesson's id): /family/trials/{session}. */
  session: string;
  /** Session tokens (the portal's cookie) for the first family's guardian (classic) and child (the kids' look). */
  guardian_token: string;
  learner_token: string;
};

export function trialAfter(): TrialAfter {
  const out = artisan(['tinker', '--execute=echo Database\\Seeders\\E2eResetSeeder::trialAfter();']);
  return JSON.parse(out.trim().split('\n').pop() ?? '{}') as TrialAfter;
}

/** The offer of the trial with this lesson ends now, as it would on its own after its hours. */
export function endTrialOffer(session: string): void {
  if (!/^[0-9a-f-]{36}$/.test(session)) throw new Error(`Not a lesson: ${session}`);
  artisan(['tinker', `--execute=echo Database\\Seeders\\E2eResetSeeder::endTrialOffer('${session}');`]);
}

/* ── Starting a course on one's own (J3) ── */

/** A plan's price in the region of this currency changes meanwhile (as the office would); returns the old one, to put back. */
export function planPrice(plan: string, currency: string, minor: number): number {
  if (!/^[0-9a-f-]{36}$/.test(plan) || !/^[A-Z]{3}$/.test(currency) || !Number.isInteger(minor)) throw new Error('Not a plan, currency and price');
  return Number(artisan(['tinker', `--execute=echo Database\\Seeders\\E2eResetSeeder::planPrice('${plan}', '${currency}', ${minor});`]).trim().split('\n').pop());
}

/** A general coupon the academy hands out (a percentage off one block); returns its code. */
export function coupon(code: string, percent: number): string {
  if (!/^[A-Z0-9-]{3,32}$/.test(code) || !Number.isInteger(percent)) throw new Error(`Not a coupon: ${code}`);
  return artisan(['tinker', `--execute=echo Database\\Seeders\\E2eResetSeeder::coupon('${code}', ${percent});`]).trim().split('\n').pop() ?? '';
}

/** An academy holiday in a start's first four weeks (E2eResetSeeder::testHoliday: the demo's own, else one this many days ahead), so a weekly time skips that week; its first day. */
export function testHoliday(days = 15): string {
  if (!Number.isInteger(days)) throw new Error(`Not a number of days: ${days}`);
  return artisan(['tinker', `--execute=echo Database\\Seeders\\E2eResetSeeder::testHoliday(${days});`]).trim().split('\n').pop() ?? '';
}

/** The end-to-end tests' holiday goes again (E2eResetSeeder::removeTestHolidays). */
export function removeTestHolidays(): number {
  return Number(artisan(['tinker', '--execute=echo Database\\Seeders\\E2eResetSeeder::removeTestHolidays();']).trim().split('\n').pop());
}

/** The courses the end-to-end families started end today (E2eResetSeeder::endTestStarts), so the demo's teachers keep their weekly room. */
export function endTestStarts(): number {
  return Number(artisan(['tinker', '--execute=echo Database\\Seeders\\E2eResetSeeder::endTestStarts();']).trim().split('\n').pop());
}

/** Earlier runs' free trials still to come are cancelled (E2eResetSeeder::freeTestTrials), so they stop filling the demo teachers' days. */
export function freeTestTrials(): number {
  return Number(artisan(['tinker', '--execute=echo Database\\Seeders\\E2eResetSeeder::freeTestTrials();']).trim().split('\n').pop());
}

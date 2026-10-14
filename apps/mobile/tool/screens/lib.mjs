// What shoot.mjs is made of: the web build served from here, the API answered
// from the tests' fixtures (build/screens/data, from data_test.dart), and a
// phone-sized Chromium that signs in by code and taps by what things are
// called (Flutter's semantics), never by where they are.
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const { chromium } = await import(path.join(root, '../web/node_modules/playwright/index.mjs'));

const types = { '.html': 'text/html', '.js': 'text/javascript', '.mjs': 'text/javascript', '.json': 'application/json', '.wasm': 'application/wasm', '.png': 'image/png', '.otf': 'font/otf', '.ttf': 'font/ttf', '.svg': 'image/svg+xml', '.wav': 'audio/wav', '.mp3': 'audio/mpeg' };

/** Serves build/web on a free port of 127.0.0.1; resolves to its address and a way to stop. */
export function serve(dir = path.join(root, 'build/web')) {
  if (!fs.existsSync(path.join(dir, 'index.html'))) throw new Error('No web build: run `flutter build web --no-web-resources-cdn` first.');
  const server = http.createServer((req, res) => {
    const name = decodeURIComponent(new URL(req.url, 'http://x').pathname);
    let file = path.join(dir, name === '/' ? 'index.html' : name);
    if (!file.startsWith(dir) || !fs.existsSync(file) || fs.statSync(file).isDirectory()) file = path.join(dir, 'index.html');
    res.writeHead(200, { 'content-type': types[path.extname(file)] ?? 'application/octet-stream' });
    fs.createReadStream(file).pipe(res);
  });
  return new Promise((done) => server.listen(0, '127.0.0.1', () => done({ url: `http://127.0.0.1:${server.address().port}/`, close: () => server.close() })));
}

/** The look's recorded answers, moved from the fixtures' October 2099 to this week (same time of day, labels rewritten). */
export function lookData(look, now) {
  const file = path.join(root, 'build/screens/data', `${look}.json`);
  if (!fs.existsSync(file)) throw new Error(`No answers for ${look}: run \`flutter test tool/screens/data_test.dart\` first.`);
  const data = JSON.parse(fs.readFileSync(file, 'utf8'));
  const today = Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate());
  const delta = today - Date.UTC(2099, 9, 12);
  const move = (v) => {
    if (Array.isArray(v)) return v.map(move);
    if (v && typeof v === 'object') {
      if (typeof v.utc === 'string' && typeof v.local === 'string' && v.utc.startsWith('2099')) {
        const offset = v.local.slice(19);
        const at = new Date(new Date(v.utc).getTime() + delta);
        const sign = offset.startsWith('-') ? -1 : 1;
        const [h, m] = offset.slice(1).split(':').map(Number);
        const wall = new Date(at.getTime() + sign * (h * 60 + m) * 60e3);
        const label = v.label ? `${new Intl.DateTimeFormat('ar-EG-u-nu-latn', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(wall)} · ${wall.toISOString().slice(11, 16)}` : v.label;
        return { ...v, utc: at.toISOString().replace('.000Z', 'Z'), local: wall.toISOString().slice(0, 19) + offset, label };
      }
      return Object.fromEntries(Object.entries(v).map(([k, x]) => [k, move(x)]));
    }
    if (typeof v === 'string' && /^2099-\d\d-\d\d$/.test(v)) return new Date(Date.parse(v + 'T00:00:00Z') + delta).toISOString().slice(0, 10);
    return v;
  };
  return { ...data, answers: move(data.answers) };
}

/** Answers the app's API calls (http://localhost:8000/api/v1/...) from [data]; sign-in by code always finds the look's account. */
export async function answer(page, data) {
  const misses = new Set();
  const academy = { id: 'a1', slug: data.academy.slug, name_ar: data.academy.name_ar, name_en: data.academy.name_en, logo_url: null, colour: null, privacy_url: null, terms_url: null };
  const byPath = {};
  for (const [key, value] of Object.entries(data.answers)) (byPath[key.split('?')[0]] ??= value);
  await page.route('http://localhost:8000/**', (route) => {
    const url = new URL(route.request().url());
    const p = url.pathname.replace('/api/v1/', '');
    const method = route.request().method();
    const reply = (status, body) => route.fulfill({ status, contentType: 'application/json', headers: { 'access-control-allow-origin': '*', 'access-control-allow-headers': '*' }, body: JSON.stringify(body) });
    if (method === 'OPTIONS') return reply(204, {});
    if (method === 'POST' && p === 'accounts/code') return reply(202, { challenge_id: 'c1', channel: 'whatsapp', sent_to: '+966•••••••04', expires_at: '2099-01-01T00:00:00Z', resend_after: 60, channels: ['whatsapp', 'sms'] });
    if (method === 'POST' && p === 'accounts/code/verify') return reply(200, { discovery_token: 'd', expires_at: null, accounts: [{ academy, user: data.user }] });
    if (method === 'POST' && p === 'accounts/sign-in') return reply(200, { token: 't', token_type: 'Bearer', expires_at: null, user: { ...data.user, academy: { ...academy, default_timezone: 'Asia/Riyadh' } } });
    if (method === 'GET') {
      const key = p + url.search;
      if (key in data.answers) return reply(200, data.answers[key]);
      if (p in byPath) return reply(200, byPath[p]);
      if (p === 'notifications') return reply(200, { data: [], meta: { unread: 0, next_cursor: null } });
    }
    misses.add(`${method} ${p}`);
    return reply(404, { type: 'not-found', title: 'Not found', status: 404 });
  });
  return misses;
}

/** A phone: 390 by 844 at twice the pixels, on Riyadh's clock, the time fixed at [now]. */
export async function phone({ lang = 'ar', now }) {
  const executablePath = process.env.PW_CHROMIUM ?? ['/opt/pw-browsers/chromium', '/opt/pw-browsers/chromium-1194/chrome-linux/chrome'].find((p) => fs.existsSync(p) && fs.statSync(p).isFile());
  const browser = await chromium.launch(executablePath ? { executablePath } : {});
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, locale: lang === 'ar' ? 'ar-EG' : 'en-GB', timezoneId: 'Asia/Riyadh' });
  const page = await context.newPage();
  await page.clock.setFixedTime(now);
  return { browser, page };
}

export const wait = (ms) => new Promise((done) => setTimeout(done, ms));

/** Turns on Flutter's semantics tree, so things can be found by what they are called. */
export async function semantics(page) {
  await page.evaluate(() => document.querySelector('flt-semantics-placeholder')?.click());
  await wait(500);
}

/** Taps the button called [name] (a string or a pattern); false when there is none. */
export async function tap(page, name, { timeout = 4000 } = {}) {
  const button = page.getByRole('button', { name }).first();
  try {
    await button.waitFor({ timeout });
  } catch {
    return false;
  }
  await button.click();
  await wait(900);
  return true;
}

/** Types [text] into the screen's one text field (Flutter's web inputs are labelled by their hint). */
export async function type(page, text) {
  const field = page.locator('input, textarea').first();
  await field.waitFor({ state: 'attached', timeout: 6000 });
  await field.focus();
  await wait(300);
  await page.keyboard.type(text);
  await wait(500);
}

/** The app's own words, from the ARB files: the same labels the screens show. */
export function words(lang) {
  return JSON.parse(fs.readFileSync(path.join(root, `lib/core/l10n/arb/app_${lang}.arb`), 'utf8'));
}

/** Through the slides, the contact, the code and the account found, to the first tab. */
export async function signIn(page, lang) {
  const w = words(lang);
  await semantics(page);
  if (lang === 'en') await tap(page, 'English');
  if (!(await tap(page, w.introSkip))) throw new Error('The slides did not come up.');
  await semantics(page);
  await type(page, '0501234504');
  if (!(await tap(page, w.sendCode))) throw new Error('No "send me a code" button.');
  await wait(1200);
  await semantics(page);
  await type(page, '123456');
  await wait(2500);
  // One account found: its card's button, "Go to {academy}".
  await semantics(page);
  const goTo = w.enterAcademy.split('{')[0].trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  if (!(await tap(page, new RegExp(`^${goTo}`)))) throw new Error('The account found did not come up.');
  await wait(4500);
  // The welcome moment closes with a tap anywhere.
  await page.mouse.click(300, 95);
  await wait(2000);
}

/** Opens [place] (a route such as /teen/journey) the way a link would. */
export async function open(page, base, place) {
  await page.goto(`${base}#${place}`);
  await wait(3500);
}

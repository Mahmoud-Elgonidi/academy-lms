import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { DESKTOP, PHONE } from './layout.mjs';
import { totp } from './totp.mjs';
import { USERS } from '../users.mjs';

/**
 * Plays a lesson's steps on the real screens and films them: Chrome's own
 * screencast hands over a frame each time the screen changes (with the moment
 * it was drawn), so the film is exactly what a person would have seen, and the
 * captions are timed against the same clock.
 */
const here = dirname(fileURLToPath(import.meta.url));
const overlay = readFileSync(join(here, 'overlay.js'), 'utf8');
const authDir = join(here, '../.auth');
const apiDir = join(here, '../../../apps/api');

const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));
const now = () => Date.now() / 1000;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** How long a caption stays up so it can be read without hurrying. */
export const readSeconds = (text) => clamp(1.4 + text.length * 0.07, 3, 12);

const deviceOf = (series) => (series.device === 'phone' ? PHONE : DESKTOP);

function contextOptions(series) {
  const d = deviceOf(series);
  return {
    viewport: d.viewport,
    deviceScaleFactor: d.scale,
    locale: 'ar-EG',
    timezoneId: series.timezone ?? 'Asia/Riyadh',
    colorScheme: 'light',
    // The live class asks for them; the browser is launched with a fake camera and mic (record.mjs).
    permissions: ['camera', 'microphone'],
    ...(series.device === 'phone' ? { isMobile: true, hasTouch: true } : {}),
  };
}

/**
 * Scripts a page loads from cdn.jsdelivr.net/npm (the live class's
 * livekit-client) are served from the same package on the npm registry,
 * kept in .cdn/, for environments that reach npm and not the CDN.
 */
const cdnDir = join(here, '../.cdn');
async function localCdn(context) {
  await context.route('https://cdn.jsdelivr.net/npm/**', async (route) => {
    const path = decodeURIComponent(new URL(route.request().url()).pathname).replace(/^\/npm\//, '');
    const [, pkg, file] = path.match(/^((?:@[^/]+\/)?[^/@]+@[^/]+)\/(.+)$/) ?? [];
    if (!pkg) return route.continue();
    const dir = join(cdnDir, pkg);
    if (!existsSync(dir)) {
      mkdirSync(dir, { recursive: true });
      const tgz = execFileSync('npm', ['pack', '-q', pkg], { cwd: cdnDir }).toString().trim().split('\n').pop();
      execFileSync('tar', ['-xzf', join(cdnDir, tgz), '-C', dir, '--strip-components=1']);
    }
    const target = join(dir, file);
    if (!existsSync(target)) return route.continue();
    await route.fulfill({ path: target, contentType: file.endsWith('.css') ? 'text/css' : 'application/javascript' });
  });
}

/**
 * The phone app (apps/mobile), for a series with `app: true`: its web build
 * (`flutter build web --no-web-resources-cdn --base-href /app/
 * --dart-define=API_BASE_URL=<base>/api/v1`, or COURSE_APP_BUILD) is served
 * under <base>/app/, on the API's own origin, so it talks to the real API.
 */
const appBuild = process.env.COURSE_APP_BUILD ?? join(here, '../../../apps/mobile/build/web');
async function serveApp(context, base) {
  if (!existsSync(join(appBuild, 'index.html'))) throw new Error(`No app build in ${appBuild}: run \`flutter build web --no-web-resources-cdn --base-href /app/ --dart-define=API_BASE_URL=${base}/api/v1\` in apps/mobile.`);
  await context.route(`${base}/app/**`, (route) => {
    let file = join(appBuild, new URL(route.request().url()).pathname.replace(/^\/app\/?/, ''));
    if (!file.startsWith(appBuild) || !existsSync(file) || statSync(file).isDirectory()) file = join(appBuild, 'index.html');
    return route.fulfill({ path: file });
  });
}

/** Turns on Flutter's semantics tree, so the app's parts can be found by what they are called. */
async function appSemantics(page) {
  await page.evaluate(() => document.querySelector('flt-semantics-placeholder')?.click()).catch(() => {});
  await sleep(500);
}

/** Signs a demo account into the app (off camera) with the academy's code and the password; its storage is kept for an hour. */
async function ensureAppSession(browser, series, user, base) {
  mkdirSync(authDir, { recursive: true });
  const file = join(authDir, `${user.email}-app.json`);
  if (existsSync(file) && Date.now() - statSync(file).mtimeMs < 3600_000) return file;
  const password = process.env.COURSE_PASSWORD;
  if (!password) throw new Error('Set COURSE_PASSWORD to the demo accounts\' password.');
  const context = await browser.newContext(contextOptions(series));
  await serveApp(context, base);
  const page = await context.newPage();
  await page.goto(`${base}/app/`);
  await sleep(4000);
  await appSemantics(page);
  await page.getByRole('button', { name: 'تخطّي' }).first().click();
  await sleep(1200);
  await page.getByRole('button', { name: 'عندك كلمة سر؟ ادخل بيها' }).click();
  await sleep(1200);
  await appSemantics(page);
  const fields = page.locator('flt-semantics input');
  for (const [i, value] of [series.academy ?? 'demo', user.email, password].entries()) {
    await fields.nth(i).click();
    await sleep(250);
    await page.keyboard.type(value);
    await sleep(250);
  }
  await page.getByRole('button', { name: /^ادخل$/ }).first().click();
  await page.getByRole('tab').first().waitFor({ timeout: 20000 }).catch(async () => {
    await appSemantics(page);
    await page.getByRole('tab').first().waitFor({ timeout: 10000 });
  });
  await context.storageState({ path: file });
  await context.close();
  return file;
}

/** The second step of a sign-in: an authenticator secret from the environment, or a command that prints the code. */
function secondFactor(user) {
  if (user.totpEnv) {
    const secret = process.env[user.totpEnv];
    if (!secret) throw new Error(`${user.email} asks for a code: set ${user.totpEnv} to the account's authenticator secret.`);
    return totp(secret);
  }
  if (user.codeCommand) return execFileSync(user.codeCommand[0], user.codeCommand.slice(1), { cwd: apiDir }).toString().trim().split(/\s+/).pop();
  return null;
}

/** Signs a demo account in once (off camera) and keeps the cookies for an hour. */
export async function ensureSession(browser, series, user, base) {
  mkdirSync(authDir, { recursive: true });
  const file = join(authDir, `${user.email}-${series.device ?? 'desktop'}.json`);
  if (existsSync(file) && Date.now() - statSync(file).mtimeMs < 3600_000) return file;
  const password = process.env.COURSE_PASSWORD;
  if (!password) throw new Error('Set COURSE_PASSWORD to the demo accounts\' password.');
  const context = await browser.newContext(contextOptions(series));
  const page = await context.newPage();
  await page.goto(`${base}/${user.panel}/login`);
  await page.fill('#form\\.email', user.email);
  await page.fill('#form\\.password', password);
  await page.locator('form button[type=submit]').first().click();
  await page.waitForLoadState('networkidle').catch(() => {});
  await sleep(800);
  if (/login|challenge|multi-factor/.test(page.url()) && (user.totpEnv || user.codeCommand)) {
    const input = page.locator('form input:not([type=hidden]):not([type=checkbox])').first();
    await input.waitFor({ timeout: 15000 });
    await input.fill(secondFactor(user));
    await page.locator('form button[type=submit]').first().click();
  }
  await page.waitForURL((u) => !/login|challenge|multi-factor/.test(u.toString()), { timeout: 20000 });
  await context.storageState({ path: file });
  await context.close();
  return file;
}

function stepsApi(page, series, rec, voice, others = []) {
  const touch = series.device === 'phone';
  const vp = deviceOf(series).viewport;
  let pointer = { x: vp.width * 0.62, y: vp.height * 0.72 };

  const resolve = (target) => {
    if (typeof target === 'function') return target(page);
    if (typeof target === 'string') return page.locator(target).first();
    return target;
  };
  const settle = async (ms = 450) => {
    await page.waitForLoadState('networkidle', { timeout: 6000 }).catch(() => {});
    await sleep(ms);
  };
  // In the app everything is drawn on a canvas: scrolling is the wheel over it, until the part is in view
  // (between the top and the tab bar).
  const appInView = async (loc) => {
    // The tab bar stays put at the bottom.
    if ((await loc.getAttribute('role').catch(() => null)) === 'tab') return;
    for (let i = 0; i < 30; i++) {
      const b = await loc.boundingBox();
      if (!b) return;
      const top = 70;
      const bottom = vp.height - 96;
      if (b.y >= top && b.y + Math.min(b.height, bottom - top) <= bottom) return;
      const dy = b.y < top ? Math.max(-320, b.y - top - 40) : Math.min(320, b.y + Math.min(b.height, bottom - top) - bottom + 40);
      await page.mouse.move(vp.width / 2, vp.height / 2);
      await page.mouse.wheel(0, dy);
      await sleep(260);
    }
  };
  const box = async (loc) => {
    await loc.waitFor({ state: series.app ? 'attached' : 'visible', timeout: 15000 });
    if (series.app) {
      await appInView(loc);
      await sleep(250);
      const b = await loc.boundingBox();
      if (!b) throw new Error(`Nothing to point at: ${loc}`);
      return b;
    }
    await loc.evaluate((el) => el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' }));
    await sleep(350);
    const b = await loc.boundingBox();
    if (!b) throw new Error(`Nothing to point at: ${loc}`);
    return b;
  };
  const moveTo = async (x, y) => {
    const dist = Math.hypot(x - pointer.x, y - pointer.y);
    const ms = Math.round(clamp(320 + dist * 0.55, 420, 950));
    await page.evaluate(([x, y, ms]) => window.__course.move(x, y, ms), [x, y, ms]);
    pointer = { x, y };
    await sleep(ms + 80);
  };
  const ring = (b) => page.evaluate((b) => window.__course.ring(b), b);

  let caption = null;
  let spoken = 0;
  const finishCaption = async () => {
    if (!caption) return;
    // With a narrator the caption stays until the voice is done (and a breath after); without one, until it could be read.
    const left = caption.start + (caption.audio ? Math.max(caption.seconds + 0.45, 2.5) : readSeconds(caption.text)) - now();
    if (left > 0) await sleep(left * 1000);
    caption.end = now();
    caption = null;
  };

  const s = {
    page,
    settle,
    /** Shows a caption; the actions after it happen while it is up, and the next one waits until this one could be read. */
    async say(text, { lead = 900 } = {}) {
      await finishCaption();
      const clip = voice?.lines[spoken++];
      if (voice && clip?.caption !== text) throw new Error(`The narration was made for other steps (expected "${clip?.caption}", got "${text}").`);
      caption = { text, start: now(), end: null, ...(clip ? { spoken: clip.text, audio: clip.file, seconds: clip.seconds } : {}) };
      rec.captions.push(caption);
      await sleep(lead);
    },
    /** Takes the caption down (a quiet moment before the end). */
    async hush() {
      await finishCaption();
    },
    /**
     * Someone else on the same page, off camera (the teacher in the live
     * class): signed in as USERS[key] on a desktop, at path; their page.
     */
    async joinAs(key, path) {
      const user = USERS[key];
      const state = await ensureSession(page.context().browser(), { device: 'desktop' }, user, rec.base);
      const other = await page.context().browser().newContext({ ...contextOptions({ device: 'desktop' }), storageState: state });
      await localCdn(other);
      others.push(other);
      const p = await other.newPage();
      await p.goto(path.startsWith('http') ? path : rec.base + path);
      await p.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
      return p;
    },
    async go(path) {
      await page.goto(path.startsWith('http') ? path : rec.base + path);
      await settle(600);
      await page.evaluate(([x, y]) => window.__course.move(x, y, 0), [pointer.x, pointer.y]);
    },
    async point(target, { hold = 1200 } = {}) {
      const loc = resolve(target);
      const b = await box(loc);
      await moveTo(b.x + b.width / 2, b.y + Math.min(b.height / 2, 22));
      await ring(b);
      await sleep(hold);
      await ring(null);
    },
    /** Shows a part of the screen with the ring only (the pointer stays where it is). */
    async show(target, { hold = 1600 } = {}) {
      const b = await box(resolve(target));
      await ring(b);
      await sleep(hold);
      await ring(null);
    },
    async click(target, { settle: wait = 700, ringFirst = true } = {}) {
      const loc = resolve(target);
      const b = await box(loc);
      const x = b.x + b.width / 2;
      const y = b.y + b.height / 2;
      await moveTo(x, y);
      if (ringFirst) {
        await ring(b);
        await sleep(touch ? 350 : 300);
      }
      await page.evaluate(([x, y]) => window.__course.ripple(x, y), [x, y]);
      await ring(null);
      await loc.click();
      await settle(wait);
    },
    async type(target, text, { delay = 55 } = {}) {
      const loc = resolve(target);
      await s.click(loc, { settle: 150 });
      if (series.app) {
        await page.keyboard.type(text, { delay });
        await sleep(400);
        return;
      }
      await loc.pressSequentially(text, { delay });
      await sleep(400);
    },
    async fill(target, text) {
      const loc = resolve(target);
      await s.click(loc, { settle: 150 });
      await loc.fill(text);
      await sleep(500);
    },
    async select(target, value) {
      const loc = resolve(target);
      const b = await box(loc);
      await moveTo(b.x + b.width / 2, b.y + b.height / 2);
      await ring(b);
      await sleep(300);
      await loc.selectOption(value);
      await ring(null);
      await settle(500);
    },
    /** Scrolls smoothly: to an element (centred), or by a number of pixels. */
    async scroll(target, { within } = {}) {
      if (series.app) {
        if (typeof target === 'number') {
          await page.mouse.move(vp.width / 2, vp.height / 2);
          for (let left = target; Math.abs(left) > 1; left -= Math.sign(left) * Math.min(Math.abs(left), 120)) {
            await page.mouse.wheel(0, Math.sign(left) * Math.min(Math.abs(left), 120));
            await sleep(70);
          }
        } else {
          // Flutter puts a part in its tree only once it comes near the screen: scroll down until it does.
          const loc = resolve(target);
          for (let i = 0; i < 20 && !(await loc.count()); i++) {
            await page.mouse.move(vp.width / 2, vp.height / 2);
            await page.mouse.wheel(0, 300);
            await sleep(300);
          }
          await loc.waitFor({ state: 'attached', timeout: 15000 });
          await appInView(loc);
        }
        await sleep(700);
        return;
      }
      if (typeof target === 'number') {
        if (within) await resolve(within).evaluate((el, dy) => el.scrollBy({ top: dy, behavior: 'smooth' }), target);
        else await page.evaluate((dy) => window.scrollBy({ top: dy, behavior: 'smooth' }), target);
      } else {
        await resolve(target).evaluate((el) => el.scrollIntoView({ block: 'center', behavior: 'smooth' }));
      }
      await sleep(1100);
    },
    async wait(ms) {
      await sleep(ms);
    },
    async key(key) {
      await page.keyboard.press(key);
      await settle(500);
    },
    /** The side menu's item with this label (opening its group first when it is folded). */
    nav: (label) => page.locator('.fi-sidebar-item').filter({ hasText: label }).locator('a, button').first(),
    button: (name) => page.getByRole('button', { name }).first(),
    link: (name) => page.getByRole('link', { name }).first(),
    text: (t) => page.getByText(t, { exact: false }).first(),
    modal: () => page.locator('.fi-modal-window:visible').last(),
    /** The app's tab with this label, its text field with this hint, and a fresh look at its semantics after a new screen. */
    tab: (name) => page.getByRole('tab', { name }).first(),
    field: (hint) => page.locator(`flt-semantics input[aria-label*="${hint}"], flt-semantics textarea[aria-label*="${hint}"]`).first(),
    semantics: () => appSemantics(page),
    /** The app's smallest part that says this. */
    says: (text) => page.locator('flt-semantics').filter({ hasText: text }).last(),
    finishCaption,
  };
  return s;
}

/**
 * Records one lesson into `dir`: frames/*.jpg with their times, and
 * recording.json (frames, captions with their spoken lines, length) for the
 * encoder. With a narration (lib/voice.mjs) each step waits for its line.
 */
export async function recordLesson({ browser, series, lesson, user, base, dir, voice = null }) {
  const storageState = series.app ? await ensureAppSession(browser, series, user, base) : await ensureSession(browser, series, user, base);
  const framesDir = join(dir, 'frames');
  mkdirSync(framesDir, { recursive: true });
  const context = await browser.newContext({ ...contextOptions(series), storageState });
  await localCdn(context);
  if (series.app) await serveApp(context, base);
  await context.addInitScript({ content: `window.__courseMode=${JSON.stringify(series.device === 'phone' ? 'touch' : 'mouse')};\n${overlay}` });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('response', (r) => { if (r.status() >= 500) errors.push(`${r.status()} ${r.url()}`); });

  await page.goto(base + (series.app ? `/app/#${lesson.start}` : lesson.start));
  await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
  if (series.app) {
    await sleep(3500);
    await appSemantics(page);
    await page.getByRole('tab').first().waitFor({ timeout: 15000 });
    await sleep(1500);
  }
  if (lesson.before) await lesson.before(page);
  await sleep(900);

  const rec = { base, captions: [], frames: [] };
  const app = deviceOf(series).app;
  const cdp = await context.newCDPSession(page);
  let n = 0;
  cdp.on('Page.screencastFrame', ({ data, metadata, sessionId }) => {
    const file = `${String(++n).padStart(6, '0')}.jpg`;
    writeFileSync(join(framesDir, file), Buffer.from(data, 'base64'));
    rec.frames.push({ file, t: metadata.timestamp });
    cdp.send('Page.screencastFrameAck', { sessionId }).catch(() => {});
  });
  const started = now();
  await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 92, maxWidth: series.device === 'phone' ? 780 : app.width, maxHeight: series.device === 'phone' ? 1688 : app.height, everyNthFrame: 1 });
  await sleep(600);

  const others = [];
  const s = stepsApi(page, series, rec, voice, others);
  try {
    await lesson.steps(s);
    await s.finishCaption();
    await sleep(1200);
  } catch (e) {
    await page.screenshot({ path: join(dir, 'failed.png') }).catch(() => {});
    throw e;
  } finally {
    await cdp.send('Page.stopScreencast').catch(() => {});
    await sleep(200);
    await context.close();
    for (const other of others) await other.close().catch(() => {});
  }
  const ended = now();
  const t0 = rec.frames.length ? rec.frames[0].t : started;
  const recording = {
    length: ended - t0,
    frames: rec.frames.map((f) => ({ file: f.file, t: f.t - t0 })),
    captions: rec.captions.map((c) => ({ text: c.text, start: Math.max(0, c.start - t0), end: (c.end ?? ended) - t0, ...(c.audio ? { spoken: c.spoken, audio: c.audio, seconds: c.seconds } : {}) })),
    voice: voice ? { intro: voice.intro, outro: voice.outro } : null,
    errors,
  };
  writeFileSync(join(dir, 'recording.json'), JSON.stringify(recording));
  // A lesson that signs out on camera ends the session kept for the others: sign in again next time.
  if (lesson.signsOut) rmSync(join(authDir, `${user.email}-${series.app ? 'app' : series.device ?? 'desktop'}.json`), { force: true });
  return recording;
}

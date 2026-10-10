import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { DESKTOP, PHONE } from './layout.mjs';
import { totp } from './totp.mjs';

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
    ...(series.device === 'phone' ? { isMobile: true, hasTouch: true } : {}),
  };
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

function stepsApi(page, series, rec) {
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
  const box = async (loc) => {
    await loc.waitFor({ state: 'visible', timeout: 15000 });
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
  const finishCaption = async () => {
    if (!caption) return;
    const left = caption.start + readSeconds(caption.text) - now();
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
      caption = { text, start: now(), end: null };
      rec.captions.push(caption);
      await sleep(lead);
    },
    /** Takes the caption down (a quiet moment before the end). */
    async hush() {
      await finishCaption();
    },
    async go(path) {
      await page.goto(rec.base + path);
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
    finishCaption,
  };
  return s;
}

/**
 * Records one lesson into `dir`: frames/*.jpg with their times, and
 * recording.json (frames, captions, length) for the encoder.
 */
export async function recordLesson({ browser, series, lesson, user, base, dir }) {
  const storageState = await ensureSession(browser, series, user, base);
  const framesDir = join(dir, 'frames');
  mkdirSync(framesDir, { recursive: true });
  const context = await browser.newContext({ ...contextOptions(series), storageState });
  await context.addInitScript({ content: `window.__courseMode=${JSON.stringify(series.device === 'phone' ? 'touch' : 'mouse')};\n${overlay}` });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('response', (r) => { if (r.status() >= 500) errors.push(`${r.status()} ${r.url()}`); });

  await page.goto(base + lesson.start);
  await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
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

  const s = stepsApi(page, series, rec);
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
  }
  const ended = now();
  const t0 = rec.frames.length ? rec.frames[0].t : started;
  const recording = {
    length: ended - t0,
    frames: rec.frames.map((f) => ({ file: f.file, t: f.t - t0 })),
    captions: rec.captions.map((c) => ({ text: c.text, start: Math.max(0, c.start - t0), end: (c.end ?? ended) - t0 })),
    errors,
  };
  writeFileSync(join(dir, 'recording.json'), JSON.stringify(recording));
  return recording;
}

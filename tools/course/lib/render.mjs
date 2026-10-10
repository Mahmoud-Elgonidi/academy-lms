import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { DESKTOP, FRAME, PHONE } from './layout.mjs';

/**
 * The still parts of a video, drawn as HTML in the browser (so Arabic is shaped
 * properly) and saved as PNG: the frame behind the screen, each caption, the
 * title card and the end card. Fonts are the platform's own (IBM Plex Sans
 * Arabic; Baloo Bhaijaan 2 for the kids' series), embedded.
 */
const here = dirname(fileURLToPath(import.meta.url));
const fonts = join(here, '../../../apps/api/public/fonts/stages');
const face = (family, dir, file, weight) =>
  `@font-face{font-family:'${family}';font-weight:${weight};src:url(data:font/woff2;base64,${readFileSync(join(fonts, dir, file)).toString('base64')}) format('woff2');}`;
const FONT_CSS = [400, 500, 700]
  .flatMap((w) => [
    face('Plex', 'ibm-plex-sans-arabic', `ibm-plex-sans-arabic-arabic-${w}-normal.woff2`, w),
    face('PlexLatin', 'ibm-plex-sans-arabic', `ibm-plex-sans-arabic-latin-${w}-normal.woff2`, w),
  ])
  .join('');
const KIDS_CSS = [
  face('Baloo', 'baloo-bhaijaan-2', 'baloo-bhaijaan-2-arabic-wght-normal.woff2', '400 800'),
  face('BalooLatin', 'baloo-bhaijaan-2', 'baloo-bhaijaan-2-latin-wght-normal.woff2', '400 800'),
].join('');

const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

const base = (body, { transparent = false, width = FRAME.width, height = FRAME.height, kids = false } = {}) => `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><style>
${FONT_CSS}${kids ? KIDS_CSS : ''}
*{box-sizing:border-box;margin:0;padding:0}
html,body{width:${width}px;height:${height}px;overflow:hidden;background:${transparent ? 'transparent' : '#0b1220'};font-family:${kids ? 'BalooLatin,Baloo,' : ''}PlexLatin,Plex,sans-serif;color:#f8fafc;-webkit-font-smoothing:antialiased}
.ltr{direction:ltr;unicode-bidi:isolate}
</style></head><body>${body}</body></html>`;

const backdrop = (color) => `<div style="position:absolute;inset:0;background:
  radial-gradient(1200px 700px at 85% -10%, ${color}33, transparent 60%),
  radial-gradient(900px 600px at 0% 110%, ${color}22, transparent 60%),
  linear-gradient(160deg,#0f172a,#0b1220 55%,#0a0f1c)"></div>`;

const topBar = (series, lesson) => `<div style="position:absolute;top:0;inset-inline:0;height:76px;display:flex;align-items:center;justify-content:space-between;padding:0 192px">
  <div style="display:flex;align-items:center;gap:16px">
    <span style="background:${series.color};color:#0b1220;font-weight:700;font-size:20px;padding:6px 16px;border-radius:999px">${esc(series.title)}</span>
    <span style="color:#94a3b8;font-size:22px">الدرس ${lesson.n}</span>
    <span style="font-size:26px;font-weight:700">${esc(lesson.title)}</span>
  </div>
  <div style="color:#64748b;font-size:20px">دليل استخدام المنصة</div>
</div>`;

/** Everything but the screen is see-through where the screen goes, with round corners (an even-odd clip path, drawn at once). */
const hole = (app) => {
  const { x, y, width: w, height: h, radius: r } = app;
  const d = `M0 0H${FRAME.width}V${FRAME.height}H0Z M${x + r} ${y}H${x + w - r}A${r} ${r} 0 0 1 ${x + w} ${y + r}V${y + h - r}A${r} ${r} 0 0 1 ${x + w - r} ${y + h}H${x + r}A${r} ${r} 0 0 1 ${x} ${y + h - r}V${y + r}A${r} ${r} 0 0 1 ${x + r} ${y}Z`;
  return `clip-path:path(evenodd, '${d}')`;
};

/** The frame around the screen: the series' colours, the lesson's title bar (and, for a phone, the phone and the lesson's name beside it). */
export function backgroundHtml(series, lesson) {
  const kids = series.font === 'kids';
  if (series.device === 'phone') {
    const { bezel, app } = PHONE;
    return base(`<div style="position:absolute;inset:0;${hole(app)}">${backdrop(series.color)}${topBar(series, lesson)}
      <div style="position:absolute;left:${bezel.x}px;top:${bezel.y}px;width:${bezel.width}px;height:${bezel.height}px;border-radius:${bezel.radius}px;background:#020617;box-shadow:0 0 0 2px #334155,0 30px 80px rgba(0,0,0,.55)"></div>
      <div style="position:absolute;left:${PHONE.caption.x}px;top:150px;width:${PHONE.caption.width}px">
        <div style="color:${series.color};font-weight:700;font-size:24px;margin-bottom:10px">${esc(lesson.who ?? series.audience)}</div>
        <div style="font-size:52px;font-weight:700;line-height:1.25">${esc(lesson.title)}</div>
      </div></div>`, { transparent: true, kids });
  }
  const { app } = DESKTOP;
  return base(`<div style="position:absolute;inset:0;${hole(app)}">${backdrop(series.color)}${topBar(series, lesson)}
    <div style="position:absolute;left:${app.x}px;top:${app.y}px;width:${app.width}px;height:${app.height}px;border-radius:${app.radius}px;box-shadow:0 0 0 1px #334155,0 24px 70px rgba(0,0,0,.5)"></div></div>`, { transparent: true, kids });
}

export function captionHtml(series, text, step, total) {
  const kids = series.font === 'kids';
  if (series.device === 'phone') {
    const { width, height } = PHONE.caption;
    return base(`<div style="width:${width}px;height:${height}px;display:flex;flex-direction:column;justify-content:center;gap:22px">
      <div style="color:#94a3b8;font-size:22px">خطوة ${step} من ${total}</div>
      <div style="border-inline-start:6px solid ${series.color};padding-inline-start:28px;font-size:${text.length > 120 ? 38 : 44}px;line-height:1.6;font-weight:500">${esc(text)}</div>
    </div>`, { transparent: true, width, height, kids });
  }
  const { width, height } = DESKTOP.caption;
  return base(`<div style="width:${width}px;height:${height}px;display:flex;align-items:center;justify-content:center;padding:0 192px">
    <div style="display:flex;align-items:center;gap:18px;width:100%">
      <span style="flex:none;color:#0b1220;background:${series.color};font-weight:700;font-size:20px;border-radius:999px;min-width:58px;text-align:center;padding:4px 10px" class="ltr">${step}/${total}</span>
      <div style="font-size:${text.length > 110 ? 28 : 33}px;line-height:1.5;font-weight:500">${esc(text)}</div>
    </div>
  </div>`, { transparent: true, width, height, kids });
}

export function titleHtml(series, lesson) {
  const learn = (lesson.learn ?? []).map((l) => `<li style="margin:10px 0">${esc(l)}</li>`).join('');
  return base(`${backdrop(series.color)}
    <div style="position:absolute;inset:0;display:flex;flex-direction:column;justify-content:center;padding:0 220px;gap:26px">
      <div style="display:flex;gap:18px;align-items:center">
        <span style="background:${series.color};color:#0b1220;font-weight:700;font-size:26px;padding:8px 22px;border-radius:999px">${esc(series.title)}</span>
        <span style="color:#94a3b8;font-size:30px">الدرس ${lesson.n} من ${series.lessons.length}</span>
      </div>
      <div style="font-size:84px;font-weight:700;line-height:1.2">${esc(lesson.title)}</div>
      ${lesson.goal ? `<div style="font-size:36px;color:#cbd5e1;line-height:1.5;max-width:1300px">${esc(lesson.goal)}</div>` : ''}
      ${learn ? `<div style="margin-top:10px"><div style="color:${series.color};font-size:28px;font-weight:700">هتتعلم في الدرس ده:</div><ul style="font-size:30px;color:#e2e8f0;padding-inline-start:36px;margin-top:6px">${learn}</ul></div>` : ''}
    </div>`, { kids: series.font === 'kids' });
}

export function endHtml(series, lesson, next) {
  return base(`${backdrop(series.color)}
    <div style="position:absolute;inset:0;display:flex;flex-direction:column;justify-content:center;align-items:center;gap:24px;text-align:center">
      <div style="width:110px;height:110px;border-radius:50%;background:${series.color};display:grid;place-items:center">
        <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="#0b1220" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
      </div>
      <div style="font-size:60px;font-weight:700">خلصنا "${esc(lesson.title)}"</div>
      <div style="font-size:34px;color:#94a3b8">${next ? `الدرس الجاي: <span style="color:#f8fafc">${esc(next.title)}</span>` : `ده آخر درس في ${esc(series.title)}`}</div>
    </div>`, { kids: series.font === 'kids' });
}

/** Draws each still with one browser page. */
export async function makeRenderer(browser) {
  const page = await browser.newPage({ deviceScaleFactor: 1 });
  return {
    async png(html, path, { width = FRAME.width, height = FRAME.height, transparent = false } = {}) {
      await page.setViewportSize({ width, height });
      await page.setContent(html, { waitUntil: 'load' });
      await page.evaluate(() => document.fonts.ready);
      await page.screenshot({ path, omitBackground: transparent, clip: { x: 0, y: 0, width, height } });
    },
    close: () => page.close(),
  };
}

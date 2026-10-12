import { expect, test as base, type Page } from '@playwright/test';

/**
 * What a test may meet in the browser and still pass: the academy's Google
 * Fonts (they need the network; the page falls back to the stage's own
 * fonts), and, without Reverb (E2E_LIVE unset), the live socket.
 */
const OUTSIDE = [/fonts\.(googleapis|gstatic)\.com/];
const ASSETS = ['script', 'stylesheet', 'image', 'font', 'media'];
const tolerated = (text: string, url: string) =>
  OUTSIDE.some((host) => host.test(url) || host.test(text)) || (!process.env.E2E_LIVE && /WebSocket/.test(text));

/**
 * Every test also fails when a page went wrong on the way: a script or
 * anything else its policy refused (src/lib/csp.ts, a game's sandbox
 * policy), an error in the console or one nobody caught, an answer of 500
 * or more, or a file the page needs (a script, a style, a picture, a font)
 * that is not there. A page that is not found on purpose is the test's to
 * check; the browser's own line about it is not counted twice.
 */
export const test = base.extend<{ pageHealth: void }>({
  pageHealth: [
    async ({ page }, use) => {
      const problems: string[] = [];
      await page.addInitScript(() => {
        document.addEventListener('securitypolicyviolation', (e) => console.error(`CSP refused ${e.blockedURI || 'inline'} (${e.violatedDirective})`));
      });
      page.on('console', (message) => {
        const text = message.text();
        const network = /^Failed to load resource/.test(text);
        if (/Content Security Policy|CSP refused/i.test(text) || (message.type() === 'error' && !network && !tolerated(text, message.location().url))) {
          problems.push(`console: ${text}`);
        }
      });
      page.on('pageerror', (error) => problems.push(`uncaught: ${error.message}`));
      page.on('response', (response) => {
        const [status, kind, url] = [response.status(), response.request().resourceType(), response.url()];
        if (status >= 500 || (status >= 400 && ASSETS.includes(kind) && !tolerated('', url))) problems.push(`${status} ${response.request().method()} ${url}`);
      });
      await use();
      expect(problems, 'what went wrong on the page').toEqual([]);
    },
    { auto: true },
  ],
});
export { expect };

/** The demo data's teacher with history (Ziad's teacher). The password is the demo's (DEMO_PASSWORD on a server). */
export const TEACHER = process.env.E2E_TEACHER ?? 'khaled@demo.test';
export const PASSWORD = process.env.E2E_PASSWORD ?? process.env.DEMO_PASSWORD ?? 'password';

/** Families in the demo data: Ziad's guardian (an overdue invoice to pay), Yousef (a kid), Ziad (middle school) and Maryam (a teen) themselves. */
export const GUARDIAN = process.env.E2E_GUARDIAN ?? 'ziad.guardian@demo.test';
export const KID = process.env.E2E_KID ?? 'yousef@demo.test';
export const STUDENT = process.env.E2E_STUDENT ?? 'ziad@demo.test';
export const TEEN = process.env.E2E_TEEN ?? 'maryam@demo.test';

export async function signInWithPassword(page: Page, login: string, password: string) {
  await page.goto('/ar/sign-in');
  await page.getByRole('tab', { name: 'بكلمة السر' }).click();
  await page.getByLabel('الإيميل أو الموبايل أو اسم المستخدم').fill(login);
  await page.getByLabel('كلمة السر').fill(password);
  await page.getByRole('button', { name: 'ادخل' }).click();
}

/** No page is wider than the screen. */
export async function fitsTheScreen(page: Page) {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(0);
}

/** No message key shows instead of its words (next-intl writes the key, such as `nav.kids.home`, when a message is missing). */
export async function noRawKeys(page: Page) {
  const keys = await page.evaluate(() => {
    const found: string[] = [];
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
      const text = node.textContent?.trim() ?? '';
      const parent = node.parentElement;
      if (!parent || parent.closest('script, style, code, pre') || !parent.checkVisibility()) continue;
      if (/^[a-z][A-Za-z]*(\.[A-Za-z_][\w-]*)+$/.test(text) && !/\.(com|test|sa|eg|org|net|png|jpg|pdf|wav)$/.test(text)) found.push(text);
    }
    return found;
  });
  expect(keys, 'message keys shown as text').toEqual([]);
}

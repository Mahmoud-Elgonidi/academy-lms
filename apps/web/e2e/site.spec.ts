import { expect, fitsTheScreen, noRawKeys, test } from './helpers';

/*
 * The pages a visitor sees before signing in: the demo academy's own page
 * at its root (its subjects, the teachers who chose to show, its plans, the
 * two forms) and the platform's page (at /platform while the demo is the
 * home academy). A form's token is good three seconds after the page gave
 * it, so each form waits that long before it is sent, as a person would.
 */

const FORM_READY_MS = 3_500;

/** A mobile number no earlier run sent (the academy keeps one request a week per number). */
const freshPhone = () => `+9665${String(Date.now()).slice(-8)}`;

test('the academy page shows what it offers and takes a free trial request', async ({ page }) => {
  await page.goto('/ar');
  await expect(page).toHaveURL(/\/ar$/);
  await expect(page.locator('h1')).toBeVisible();
  await expect(page.getByRole('banner').getByRole('link', { name: 'ادخل' })).toHaveAttribute('href', '/ar/sign-in');

  // Its subjects with their levels, the teachers who chose to show, its plans with prices.
  await expect(page.locator('#subjects article').first()).toBeVisible();
  await expect(page.locator('#subjects .ls-ladder li').first()).toBeVisible();
  await expect(page.locator('#teachers article').first()).toBeVisible();
  await expect(page.locator('#pricing article').first()).toBeVisible();
  // Never a teacher's (or anyone's) address or number.
  const words = await page.locator('main').innerText();
  expect(words).not.toMatch(/@demo\.test|\+?\d{9,}/);
  await fitsTheScreen(page);
  await noRawKeys(page);

  // Sent empty, the form says what is missing; filled, it goes to the academy.
  const form = page.locator('#ls-panel-trial form');
  await page.waitForTimeout(FORM_READY_MS);
  await form.getByRole('button', { name: 'ابعت' }).click();
  await expect(form.getByRole('alert')).toBeVisible();
  await form.getByLabel('اسمك').fill('ولي أمر من الموقع');
  await form.getByLabel('الموبايل (واتساب)').fill(freshPhone());
  await form.getByLabel('اسم الطفل').fill('سلمى');
  await form.getByRole('button', { name: 'ابعت' }).click();
  await expect(page.getByRole('status').getByText('وصلنا طلبك')).toBeVisible();
});

test('a teacher asks to teach at the academy from its page', async ({ page }) => {
  await page.goto('/ar#teach');
  const tab = page.getByRole('tab', { name: 'درّس معانا' });
  await expect(tab).toHaveAttribute('aria-selected', 'true');
  const form = page.locator('#ls-panel-teach form');
  await page.waitForTimeout(FORM_READY_MS);
  await form.getByLabel('اسمك').fill('مدرّسة من الموقع');
  await form.getByLabel('الموبايل (واتساب)').fill(freshPhone());
  await form.locator('.ls-chip').first().click();
  await form.getByLabel('سنين الخبرة').fill('6');
  await form.getByRole('button', { name: 'ابعت' }).click();
  await expect(page.getByRole('status').getByText('وصلنا طلبك')).toBeVisible();
});

test('the academy page in English, and still whole without motion', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto('/en');
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
  await expect(page.getByRole('link', { name: 'Book a free trial lesson' })).toBeVisible();
  // Nothing waits for a scroll to show itself.
  const last = page.locator('[data-reveal]').last();
  expect(await last.evaluate((part) => getComputedStyle(part).opacity)).toBe('1');
  await fitsTheScreen(page);
  await noRawKeys(page);
});

test('the platform page shows its plans and takes a request to join', async ({ page }) => {
  await page.goto('/ar/platform');
  await expect(page.locator('h1')).toBeVisible();
  await expect(page.locator('#pricing article').first()).toBeVisible();
  await fitsTheScreen(page);
  await noRawKeys(page);

  const form = page.locator('#join form');
  await page.waitForTimeout(FORM_READY_MS);
  await form.getByLabel('اسم الأكاديمية').fill('أكاديمية من الاختبار');
  await form.getByLabel('اسمك').fill('صاحب أكاديمية');
  await form.getByLabel('الإيميل').fill(`owner-${Date.now()}@example.test`);
  await form.getByRole('button', { name: 'ابعت الطلب' }).click();
  await expect(page.getByRole('status').getByText('وصلنا طلبك')).toBeVisible();
});

test.describe('someone signed in', () => {
  test.use({ storageState: 'e2e/.auth/teacher.json' });

  test('starts in their own area, not on the page', async ({ page }) => {
    await page.goto('/ar');
    await expect(page).toHaveURL(/\/ar\/teach$/);
  });
});

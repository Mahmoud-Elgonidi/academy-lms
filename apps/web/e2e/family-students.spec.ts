import { fixtures, lessonNow } from './backend';
import { expect, fitsTheScreen, noRawKeys, test } from './helpers';
import { familyPages } from './pages';

test.describe('a kid (Yousef)', () => {
  test.use({ storageState: 'e2e/.auth/kid.json' });

  test('has their journey and big tabs, and fits a phone', async ({ page }) => {
    test.setTimeout(120_000);
    for (const path of familyPages('kids').map((p) => `/ar${p}`)) {
      const response = await page.goto(path);
      expect(response?.status(), path).toBe(200);
      await expect(page.locator('h1').first(), path).toBeVisible();
      await fitsTheScreen(page);
      await noRawKeys(page);
    }
    await page.goto('/ar/family');
    await expect(page.locator('[data-stage="kids"]')).toBeVisible();
    const tabs = page.getByRole('navigation', { name: 'القائمة' }).last();
    await expect(tabs.getByRole('link', { name: 'رحلتي' })).toBeVisible();
    await expect(tabs.getByRole('link', { name: 'تحدّث مع معلّمك' })).toBeVisible();
    await expect(tabs.getByRole('link', { name: 'مهماتي' })).toHaveCount(0);
    // No zone chip in the kids' look.
    await expect(page.getByTestId('zone-chip')).toHaveCount(0);
  });

  test('tells a grown-up at the academy with one big button, no words needed', async ({ page }) => {
    await page.goto('/ar/family/tell-us');
    await expect(page.locator('h1')).toHaveText('هل أحزنك شيء؟');
    const form = page.locator('form');
    // A child's categories, big; no "someone is in danger now" for a child to tick.
    await expect(form.getByRole('checkbox')).toHaveCount(0);
    await form.locator('label:has(input[name="category"])').first().click();
    await form.getByRole('button', { name: 'أرسل' }).click();
    await expect(page.getByText(/^رقم #?\d+$/).first()).toBeVisible();
    await fitsTheScreen(page);
  });

  test('never sees money, cancelling or rating', async ({ page }) => {
    await page.goto('/ar/family/money');
    await expect(page).toHaveURL(/\/ar\/family$/);
    for (const when of ['', '?when=past']) {
      await page.goto(`/ar/family/lessons${when}`);
      await expect(page.locator('h1')).toBeVisible();
      await expect(page.getByRole('button', { name: 'إلغاء الحصة' })).toHaveCount(0);
      await expect(page.getByRole('button', { name: 'قيّم الحصة' })).toHaveCount(0);
    }
  });
});

test.describe('a middle-school student (Ziad)', () => {
  test.use({ storageState: 'e2e/.auth/student.json' });

  test('has their journey and levels, and fits a phone', async ({ page }) => {
    test.setTimeout(120_000);
    for (const path of familyPages('middle').map((p) => `/ar${p}`)) {
      const response = await page.goto(path);
      expect(response?.status(), path).toBe(200);
      await expect(page.locator('h1').first(), path).toBeVisible();
      await fitsTheScreen(page);
      await noRawKeys(page);
    }
    // The zone chip, in every look but the kids'.
    await expect(page.getByTestId('zone-chip').getByRole('button', { name: /^توقيتك: / })).toBeVisible();
    await expect(page.locator('[data-stage="middle"]')).toBeVisible();
    const tabs = page.getByRole('navigation', { name: 'القائمة' }).last();
    await expect(tabs.getByRole('link', { name: 'الرسائل' })).toBeVisible();
    await expect(tabs.getByRole('link', { name: 'تحدياتي' })).toHaveCount(0);
  });

  test('goes into the lesson that is on now, on its outside link', async ({ page, context }) => {
    await context.route('https://meet.google.com/**', (route) => route.fulfill({ contentType: 'text/html', body: '<title>Meet</title>' }));
    const id = lessonNow('student');
    await page.goto('/ar/family/lessons');
    await page.locator(`a[href$="/family/lessons/${id}"]`).click();
    await expect(page).toHaveURL(new RegExp(`/ar/family/lessons/${id}$`));
    await expect(page.locator('h1')).toHaveText(/^مع /);
    await fitsTheScreen(page);
    const tab = page.waitForEvent('popup');
    await page.getByRole('button', { name: 'ادخل الغرفة' }).click();
    await (await tab).waitForURL(/^https:\/\/meet\.google\.com\//);
    await expect(page.getByRole('link', { name: 'افتح رابط الحصة' })).toBeVisible();
  });

  test('hands homework in in words', async ({ page }) => {
    await page.goto('/ar/family/homework');
    // Set by Khaled for this run (E2eResetSeeder), in words.
    const card = page.getByRole('article').filter({ hasText: fixtures().homework.hand_in });
    await expect(card).toHaveCount(1);
    await card.getByRole('textbox').fill('حللت التمرين كله وراجعته مرتين.');
    await card.getByRole('button', { name: 'سلّم', exact: true }).click();
    await expect(page.getByText('تم التسليم. سيصحّحه المعلّم.')).toBeVisible();
    // What was handed in shows on the homework, now waiting for the teacher.
    await expect(page.getByText('حللت التمرين كله وراجعته مرتين.').first()).toBeVisible();
  });

  test('sees the teacher\'s mark arrive without reloading', async ({ page, browser }) => {
    test.skip(!process.env.E2E_LIVE, 'needs Reverb and the live relay (E2E_LIVE=1)');
    // What Ziad handed in just before, waiting for the teacher's mark.
    const title = fixtures().homework.hand_in;
    await page.goto('/ar/family/homework');
    const card = page.getByRole('article').filter({ hasText: title });
    await expect(card.getByText('حللت التمرين كله وراجعته مرتين.')).toBeVisible();
    await expect(card.getByText('رأي المعلّم')).toHaveCount(0);
    // The page's socket is on Ziad's own channel before the teacher marks.
    await page.waitForFunction(() => performance.getEntriesByType('resource').some((r) => r.name.includes('/bff/realtime/auth')));
    let reloaded = false;
    page.on('load', () => (reloaded = true));

    // The teacher marks it in their own browser.
    const teacher = await browser.newContext({ storageState: 'e2e/.auth/teacher.json' });
    const marking = await teacher.newPage();
    await marking.goto('/ar/teach/marking');
    const handed = marking.getByRole('article').filter({ hasText: title }).first();
    await handed.getByRole('radio', { name: '5' }).click();
    await handed.getByLabel('كلمتك للطالب').fill('ممتاز يا زياد، استمر هكذا.');
    await handed.getByRole('button', { name: 'سجّل التصحيح' }).click();
    await expect(marking.getByRole('article').filter({ hasText: title })).toHaveCount(0);
    await teacher.close();

    await expect(card.getByText('رأي المعلّم')).toBeVisible({ timeout: 10_000 });
    await expect(card.getByText('ممتاز يا زياد، استمر هكذا.')).toBeVisible();
    expect(reloaded).toBe(false);
  });
});

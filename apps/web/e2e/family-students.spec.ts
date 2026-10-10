import { expect, fitsTheScreen, test } from './helpers';

const PAGES = ['/ar/family', '/ar/family/challenges', '/ar/family/lessons', '/ar/family/homework', '/ar/family/progress', '/ar/family/notifications', '/ar/family/more'];

test.describe('a kid (Yousef)', () => {
  test.use({ storageState: 'e2e/.auth/kid.json' });

  test('has their journey, missions and big tabs, and fits a phone', async ({ page }) => {
    for (const path of PAGES) {
      const response = await page.goto(path);
      expect(response?.status(), path).toBe(200);
      await expect(page.locator('h1').first(), path).toBeVisible();
      await fitsTheScreen(page);
    }
    await page.goto('/ar/family');
    await expect(page.locator('[data-stage="kids"]')).toBeVisible();
    const tabs = page.getByRole('navigation', { name: 'القايمة' }).last();
    await expect(tabs.getByRole('link', { name: 'رحلتي' })).toBeVisible();
    await expect(tabs.getByRole('link', { name: 'مهماتي' })).toBeVisible();
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

  test('collects a finished mission and sees what it brought', async ({ page }) => {
    await page.goto('/ar/family/challenges');
    await expect(page.locator('h1')).toHaveText('مهمات الأسبوع');
    const collect = page.getByRole('button', { name: /^استلم / });
    test.skip((await collect.count()) === 0, 'nothing to collect in this data');
    const before = await collect.count();
    await collect.first().click();
    await expect(page.getByRole('status').filter({ hasText: /^خدت / })).toBeVisible();
    await expect(collect).toHaveCount(before - 1);
  });
});

test.describe('a middle-school student (Ziad)', () => {
  test.use({ storageState: 'e2e/.auth/student.json' });

  test('has their quests and levels, and fits a phone', async ({ page }) => {
    for (const path of PAGES) {
      const response = await page.goto(path);
      expect(response?.status(), path).toBe(200);
      await expect(page.locator('h1').first(), path).toBeVisible();
      await fitsTheScreen(page);
    }
    await expect(page.locator('[data-stage="middle"]')).toBeVisible();
    const tabs = page.getByRole('navigation', { name: 'القايمة' }).last();
    await expect(tabs.getByRole('link', { name: 'تحدياتي' })).toBeVisible();
  });

  test('hands homework in in words', async ({ page }) => {
    await page.goto('/ar/family/homework');
    const open = page.getByRole('article').filter({ has: page.getByRole('textbox') });
    test.skip((await open.count()) === 0, 'no open homework in this data');
    const card = open.first();
    await card.getByRole('textbox').fill('حليت التمرين كله وراجعته مرتين.');
    await card.getByRole('button', { name: 'سلّم', exact: true }).click();
    await expect(page.getByText('اتسلّم. المدرّس هيصححه.')).toBeVisible();
    // What was handed in shows on the homework, now waiting for the teacher.
    await expect(page.getByText('حليت التمرين كله وراجعته مرتين.').first()).toBeVisible();
  });

  test('sees the teacher\'s mark arrive without reloading', async ({ page, browser }) => {
    test.skip(!process.env.E2E_LIVE, 'needs Reverb and the live relay (E2E_LIVE=1)');
    const title = 'حل تمرين ص 20';
    await page.goto('/ar/family/homework');
    const card = page.getByRole('article').filter({ hasText: title });
    test.skip((await card.count()) === 0 || (await card.getByText('رأي المدرّس').count()) > 0, 'nothing waiting for a mark in this data');
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
    await handed.getByLabel('كلمتك للطالب').fill('ممتاز يا زياد، كمّل كده.');
    await handed.getByRole('button', { name: 'سجّل التصحيح' }).click();
    await expect(marking.getByRole('article').filter({ hasText: title })).toHaveCount(0);
    await teacher.close();

    await expect(card.getByText('رأي المدرّس')).toBeVisible({ timeout: 10_000 });
    await expect(card.getByText('ممتاز يا زياد، كمّل كده.')).toBeVisible();
    expect(reloaded).toBe(false);
  });
});

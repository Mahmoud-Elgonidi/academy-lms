import { expect, test } from '@playwright/test';
import { fitsTheScreen } from './helpers';

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
});

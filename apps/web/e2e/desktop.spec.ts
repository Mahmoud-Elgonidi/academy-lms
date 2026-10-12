import type { Page } from '@playwright/test';
import { expect, fitsTheScreen, noRawKeys, test } from './helpers';
import { TEACHER_PAGES, familyPages } from './pages';

/*
 * The same portals on a wide screen (1366px): the side menu instead of the
 * phone's tab bar, and every page of each look still opening, fitting and
 * saying its words. Nothing here changes the demo's data.
 */

// Each test walks every page of a look, twice.
test.describe.configure({ timeout: 240_000 });

/** Opens each page and checks it, with the side menu (not the tab bar) to get around. */
async function tour(page: Page, paths: string[], locale: 'ar' | 'en') {
  for (const path of paths) {
    const response = await page.goto(`/${locale}${path}`);
    expect(response?.status(), path).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
    await expect(page.locator('h1').first(), path).toBeVisible();
    const menus = page.getByRole('navigation', { name: locale === 'ar' ? 'القايمة' : 'Menu' });
    // The side menu shows, and the phone's tab bar keeps out of the way.
    await expect(menus.first(), path).toBeVisible();
    if ((await menus.count()) > 1) await expect(menus.last(), path).toBeHidden();
    await fitsTheScreen(page);
    await noRawKeys(page);
  }
}

test.describe('the teacher (Khaled)', () => {
  test.use({ storageState: 'e2e/.auth/teacher.json' });

  test('every page on a wide screen, in Arabic and English', async ({ page }) => {
    await tour(page, TEACHER_PAGES.map((p) => `/teach${p}`), 'ar');
    await tour(page, ['/teach', '/teach/week', '/teach/reports', '/teach/hours'], 'en');
    // The side menu gets around.
    await page.goto('/ar/teach');
    await page.getByRole('navigation', { name: 'القايمة' }).first().getByRole('link', { name: 'الدعم' }).click();
    await expect(page).toHaveURL(/\/ar\/teach\/help$/);
  });
});

test.describe('Ziad\'s guardian (classic)', () => {
  test.use({ storageState: 'e2e/.auth/guardian.json' });

  test('every page on a wide screen, in Arabic and English', async ({ page }) => {
    const pages = familyPages('classic', { money: true, guardian: true });
    await tour(page, pages, 'ar');
    await tour(page, pages, 'en');
    await page.goto('/ar/family');
    await page.getByRole('navigation', { name: 'القايمة' }).first().getByRole('link', { name: 'الفلوس' }).click();
    await expect(page).toHaveURL(/\/ar\/family\/money$/);
  });
});

for (const [who, stage, home] of [
  ['kid', 'kids', 'رحلتي'],
  ['student', 'middle', 'رحلتي'],
  ['teen', 'teen', 'رحلتي'],
] as const) {
  test.describe(`a student in the ${stage} look`, () => {
    test.use({ storageState: `e2e/.auth/${who}.json` });

    test('every page on a wide screen, in Arabic and English', async ({ page }) => {
      await tour(page, familyPages(stage), 'ar');
      await tour(page, familyPages(stage), 'en');
      await page.goto('/ar/family/lessons');
      await expect(page.locator(`[data-stage="${stage}"]`)).toBeVisible();
      await page.getByRole('navigation', { name: 'القايمة' }).first().getByRole('link', { name: home }).click();
      await expect(page).toHaveURL(/\/ar\/family$/);
    });
  });
}

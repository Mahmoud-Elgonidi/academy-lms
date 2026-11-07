import type { Page } from '@playwright/test';
import { academyModules } from './backend';
import { expect, fitsTheScreen, noRawKeys, test } from './helpers';

/*
 * Academy modules (docs/architecture/02, section 28): the owner hides the
 * games and the messages (Settings › Academy modules, SaveAcademyModules).
 * Every entry to them leaves the family's and the teacher's portals: the
 * menus, the phone's tab bar and "more", the journey's way to the album. A
 * page reached directly says calmly that the academy turned it off, never an
 * error. Then both come back, and so do their entries.
 */

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => {
  expect(academyModules({ games: false, messaging: false })).toEqual(expect.arrayContaining(['games', 'messaging']));
});

test.afterAll(() => {
  academyModules({ games: true, messaging: true });
});

const menu = (page: Page) => page.getByRole('navigation', { name: 'القائمة' });

/** The calm word in place of a page whose area the academy turned off. */
async function saysOff(page: Page, path: string) {
  const response = await page.goto(path);
  expect(response?.status(), path).toBe(200);
  const off = page.locator('[data-area-off="academy"]');
  await expect(off, path).toBeVisible();
  await expect(off).toContainText('أوقفت الأكاديمية هذا القسم');
  await fitsTheScreen(page);
  await noRawKeys(page);
}

test.describe('a guardian (Ziad’s), on a phone', () => {
  test.use({ storageState: 'e2e/.auth/guardian.json' });

  test('loses the messages and the album, and a direct visit says the academy turned them off', async ({ page }) => {
    await page.goto('/ar/family/more');
    await expect(page.locator('main a[href$="/family/notifications"]')).toBeVisible();
    await expect(page.locator('a[href$="/family/messages"]')).toHaveCount(0);
    await expect(page.locator('main a[href$="/family/help"]')).toHaveCount(1);
    await noRawKeys(page);

    // The journey stays (the curriculum is on), without the games' album.
    await page.goto('/ar/family/journey');
    await expect(page.locator('h1').first()).toBeVisible();
    await expect(page.locator('a[href*="/family/games/album"]')).toHaveCount(0);

    await saysOff(page, '/ar/family/messages');
    await saysOff(page, '/ar/family/games/album');
    await page.screenshot({ path: shot('portal-family-off.png'), fullPage: true });
  });
});

test.describe('a child in the kids’ look (Yousef)', () => {
  test.use({ storageState: 'e2e/.auth/kid.json' });

  test('has homework on the tab bar where the messages were', async ({ page }) => {
    await page.goto('/ar/family');
    const tabs = menu(page).last();
    await expect(tabs.locator('a[href$="/family/homework"]')).toBeVisible();
    await expect(tabs.locator('a[href$="/family/messages"]')).toHaveCount(0);
    await expect(page.locator('a[href*="/family/games/album"]')).toHaveCount(0);
    await page.screenshot({ path: shot('portal-kids-tabs.png') });
  });
});

test.describe('the teacher (Khaled)', () => {
  test.use({ storageState: 'e2e/.auth/teacher.json' });

  test('loses the messages from the side menu and "more"; the page says so', async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.goto('/ar/teach');
    await expect(menu(page).first().locator('a[href$="/teach/week"]')).toBeVisible();
    await expect(menu(page).first().locator('a[href$="/teach/messages"]')).toHaveCount(0);
    await page.screenshot({ path: shot('portal-teach-menu.png') });

    await page.goto('/ar/teach/more');
    await expect(page.locator('main a[href$="/teach/hours"]')).toBeVisible();
    await expect(page.locator('main a[href$="/teach/messages"]')).toHaveCount(0);

    await saysOff(page, '/ar/teach/messages');
    await page.screenshot({ path: shot('portal-teach-off.png') });
  });

  test('gets them back once the academy shows them again', async ({ page }) => {
    academyModules({ games: true, messaging: true });
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.goto('/ar/teach');
    await expect(menu(page).first().locator('a[href$="/teach/messages"]')).toBeVisible();
    await page.goto('/ar/teach/messages');
    await expect(page.locator('[data-area-off]')).toHaveCount(0);
    await expect(page.locator('h1')).toHaveText('الرسائل');
  });
});

/** Screenshots go where E2E_SHOTS says (else Playwright's own output folder). */
function shot(name: string): string {
  return `${process.env.E2E_SHOTS ?? 'test-results'}/${name}`;
}

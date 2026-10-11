import { expect, fitsTheScreen, test } from './helpers';
import type { Page } from '@playwright/test';

/** Plays the round the page opened, through the game's own bridge: every item right first time, then the end. */
async function playRound(page: Page) {
  const frame = page.locator('iframe.lms-game-frame');
  await expect(frame).toHaveAttribute('sandbox', 'allow-scripts');
  await expect(frame).toHaveAttribute('src', /^\/bff\/games\/[0-9a-f-]{36}$/);
  const game = (await frame.elementHandle())!;
  const inside = (await game.contentFrame())!;
  const items = await inside.evaluate(() => new Promise<string[]>((done) => (window as unknown as { LMS: { onStart(fn: (r: { items: { id: string }[] }) => void): void } }).LMS.onStart((r) => done(r.items.map((i) => String(i.id))))));
  expect(items.length).toBeGreaterThan(0);
  // No way to the page around it (its unique origin); no network either, by its own policy (the test below).
  expect(
    await inside.evaluate(() => {
      try {
        void window.parent.document.title;
        return 'reached';
      } catch {
        return 'blocked';
      }
    }),
  ).toBe('blocked');
  await inside.evaluate((ids) => {
    const lms = (window as unknown as { LMS: { answer(id: string, right: boolean): void; finish(): void } }).LMS;
    ids.forEach((id) => lms.answer(id, true));
    lms.finish();
  }, items);
}

test.describe('a kid (Yousef)', () => {
  test.use({ storageState: 'e2e/.auth/kid.json' });

  test('has the islands as a tab, opens an island, and plays a round in a sandboxed frame', async ({ page }) => {
    const response = await page.goto('/ar/family/games');
    expect(response?.status()).toBe(200);
    await expect(page.locator('.gw-map')).toBeVisible();
    await fitsTheScreen(page);
    const tabs = page.getByRole('navigation', { name: 'القايمة' }).last();
    await expect(tabs.getByRole('link', { name: /الجزر/ })).toHaveAttribute('aria-current', 'page');

    // A locked island says why instead of opening.
    await page.locator('[data-island="volcano"]').click();
    await expect(page.locator('.gw-toast')).toContainText('جزيرة سرّية');

    const island = page.locator('.gw-isl[aria-label$="مفتوحة"], .gw-isl[aria-label$="فيها الواجب"]').first();
    await island.click();
    const sheet = page.getByRole('dialog');
    await expect(sheet.locator('h1')).toBeVisible();
    test.skip((await page.locator('.gw-sheet-note').count()) > 0, 'no play time left today in this data');
    await sheet.locator('.gw-gamecard[aria-disabled="false"]').first().click();
    await playRound(page);
    await expect(page.locator('.gw-done, .gw-hatch')).toBeVisible();
  });

  test('keeps the album of creatures', async ({ page }) => {
    await page.goto('/ar/family/games/album');
    await expect(page.locator('h1')).toHaveText('ألبوم كائناتي');
    await fitsTheScreen(page);
    await page.locator('.gw-ctile[data-found="yes"]').first().click();
    await expect(page.getByRole('dialog').getByRole('button', { name: 'اسمع صوته' }).first()).toBeVisible();
  });

  test('gets the game page with its own sandbox and nothing from outside', async ({ page }) => {
    await page.goto('/ar/family/games');
    const island = page.locator('.gw-isl[aria-label$="مفتوحة"], .gw-isl[aria-label$="فيها الواجب"]').first();
    await island.click();
    test.skip((await page.locator('.gw-sheet-note').count()) > 0, 'no play time left today in this data');
    await page.getByRole('dialog').locator('.gw-gamecard[aria-disabled="false"]').first().click();
    const src = await page.locator('iframe.lms-game-frame').getAttribute('src');
    const answer = await page.request.get(src!);
    expect(answer.status()).toBe(200);
    const policy = answer.headers()['content-security-policy'] ?? '';
    expect(policy).toContain("connect-src 'none'");
    expect(policy).toContain('sandbox allow-scripts');
    expect(policy).toContain("frame-ancestors 'self'");
    expect((await page.request.get('/bff/games/not-a-version')).status()).toBe(404);
  });
});

test.describe('a middle-school student (Ziad)', () => {
  test.use({ storageState: 'e2e/.auth/student.json' });

  test('races themself on their first quest', async ({ page }) => {
    const response = await page.goto('/ar/family/games');
    expect(response?.status()).toBe(200);
    await expect(page.locator('.gw-hub[data-look="middle"]')).toBeVisible();
    await expect(page.locator('h1')).toHaveText('سباق مع نفسك');
    await fitsTheScreen(page);
    const go = page.getByRole('button', { name: 'يلا' });
    test.skip((await go.count()) === 0 || (await go.isDisabled()), 'no quest or no play time in this data');
    await go.click();
    await playRound(page);
    await expect(page.locator('.gw-hub-over')).toBeVisible();
  });
});

test.describe('a guardian (Ziad\'s)', () => {
  test.use({ storageState: 'e2e/.auth/guardian.json' });

  test('sets the minutes a day and sees what was practised, with no pay or KPIs', async ({ page }) => {
    const response = await page.goto('/ar/family/games');
    expect(response?.status()).toBe(200);
    await expect(page.locator('h1')).toHaveText('ألعاب ولادك');
    await fitsTheScreen(page);
    await expect(page.getByText('مفيش شراء جوه الألعاب')).toBeVisible();
    const choice = page.getByRole('radio', { name: '15 د' });
    await choice.click();
    await expect(page.getByRole('status').filter({ hasText: 'اتحفظ.' })).toBeVisible();
    await expect(choice).toHaveAttribute('aria-checked', 'true');
    // Back to the academy's own minutes.
    await page.getByRole('radio', { name: 'الأكاديمية' }).click();
    await expect(page.getByRole('radio', { name: 'الأكاديمية' })).toHaveAttribute('aria-checked', 'true');
    // The child's album, to look at only.
    await page.goto('/ar/family/games/album');
    await expect(page.getByRole('button', { name: 'خليه صاحبك' })).toHaveCount(0);
  });
});

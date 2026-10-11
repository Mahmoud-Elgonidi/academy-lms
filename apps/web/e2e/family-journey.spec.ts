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
  // No way to the page around it (its unique origin).
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

  test('has the journey as their home: the islands of their course, and the same at /family/journey', async ({ page }) => {
    const response = await page.goto('/ar/family');
    expect(response?.status()).toBe(200);
    await expect(page.locator('.gw-map')).toBeVisible();
    await expect(page.locator('h1')).toHaveText(/^رحلة /);
    await fitsTheScreen(page);
    const tabs = page.getByRole('navigation', { name: 'القايمة' }).last();
    await expect(tabs.getByRole('link', { name: 'رحلتي' })).toHaveAttribute('aria-current', 'page');
    await expect(tabs.getByRole('link', { name: 'حصصي' })).toBeVisible();
    await expect(tabs.getByRole('link', { name: 'كلّم مدرسك' })).toBeVisible();
    // No islands, missions or badges tabs any more.
    await expect(tabs.getByRole('link', { name: /الجزر|مهماتي|إنجازاتي/ })).toHaveCount(0);
    // Each island is a level of the course; the one they are on says so.
    await expect(page.locator('.gw-isl[data-state="here"]')).toHaveCount(1);
    await expect(page.getByRole('heading', { name: 'مهماتك' })).toBeVisible();

    await page.goto('/ar/family/journey');
    await expect(page.locator('.gw-map')).toBeVisible();
    await expect(tabs.getByRole('link', { name: 'رحلتي' })).toHaveAttribute('aria-current', 'page');
  });

  test('opens the island they are on, a stop on its path, and plays a round of its games in a sandboxed frame', async ({ page }) => {
    await page.goto('/ar/family');
    await page.locator('.gw-isl[data-state="here"]').click();
    const island = page.locator('.gw-open');
    await expect(island).toBeVisible();
    await expect(island.locator('.jr-stop').first()).toBeVisible();
    await island.locator('.jr-stop[data-mark="here"]').click();
    const sheet = page.locator('.jr-sheet');
    await expect(sheet).toBeVisible();
    await expect(sheet.locator('h2')).toBeVisible();
    const games = sheet.locator('.gw-gamecard');
    test.skip((await games.count()) === 0 || (await sheet.locator('.gw-sheet-note').count()) > 0, 'no game or no play time left today in this data');
    await games.first().click();
    await playRound(page);
    await expect(page.locator('.gw-done, .gw-hatch')).toBeVisible();
  });

  test('opens the week\'s chest when it is ready', async ({ page }) => {
    await page.goto('/ar/family');
    const chest = page.locator('button.jr-chest');
    test.skip((await chest.count()) === 0, 'no chest to open in this data');
    await chest.click();
    await expect(page.getByRole('status').filter({ hasText: /^خدت / })).toBeVisible();
    await expect(page.locator('button.jr-chest')).toHaveCount(0);
  });

  test('hatches the egg the journey\'s stars filled: three taps, a name, then the album', async ({ page }) => {
    await page.goto('/ar/family');
    const egg = page.locator('button.jr-egg');
    test.skip((await egg.count()) === 0, 'no egg ready in this data');
    await egg.click();
    // The egg wobbles while it waits for its taps.
    const shell = page.locator('.gw-hatch button[aria-label^="البيضة"]');
    for (let i = 0; i < 3; i++) await shell.click({ force: true });
    await expect(page.locator('#gw-hatch-title')).toHaveText('فقست!');
    await page.getByRole('button', { name: 'حطّه في الألبوم' }).click();
    await expect(page).toHaveURL(/\/ar\/family\/games\/album\?new=/);
  });

  test('keeps the album of creatures, and old links lead to the journey', async ({ page }) => {
    await page.goto('/ar/family/games/album');
    await expect(page.locator('h1')).toHaveText('ألبوم كائناتي');
    await fitsTheScreen(page);
    await page.goto('/ar/family/games');
    await expect(page).toHaveURL(/\/ar\/family\/journey$/);
    await page.goto('/ar/family/challenges');
    await expect(page).toHaveURL(/\/ar\/family\/journey$/);
    // A homework that is not there to play says so on the journey.
    await page.goto('/ar/family/games?homework=00000000-0000-0000-0000-000000000000');
    await expect(page).toHaveURL(/\/ar\/family\/journey\?homework=/);
    await expect(page.getByRole('status').filter({ hasText: 'التدريب ده مش متاح' })).toBeVisible();
  });
});

test.describe('a middle-school student (Ziad)', () => {
  test.use({ storageState: 'e2e/.auth/student.json' });

  test('has their stars to the next rank, missions and levels, and races themself on a lesson', async ({ page }) => {
    const response = await page.goto('/ar/family');
    expect(response?.status()).toBe(200);
    await expect(page.locator('[data-stage="middle"]')).toBeVisible();
    await expect(page.locator('h1')).toHaveText(/^أهلًا يا /);
    await expect(page.getByRole('heading', { name: 'المستويات' })).toBeVisible();
    await fitsTheScreen(page);
    const tabs = page.getByRole('navigation', { name: 'القايمة' }).last();
    await expect(tabs.getByRole('link', { name: 'رحلتي' })).toHaveAttribute('aria-current', 'page');
    await expect(tabs.getByRole('link', { name: 'الرسايل' })).toBeVisible();
    await expect(page.getByText('نجومك')).toBeVisible();

    const lesson = page.locator('[data-station]:not([data-games="0"])');
    test.skip((await lesson.count()) === 0, 'no lesson with games in this data');
    await lesson.first().click();
    const sheet = page.getByRole('dialog');
    await expect(sheet).toBeVisible();
    const play = sheet.getByRole('button', { name: 'العب', exact: true });
    test.skip((await play.count()) === 0 || (await play.first().isDisabled()), 'no play time in this data');
    await play.first().click();
    await playRound(page);
    // Its result, or a creature hatching when the round filled the egg.
    await expect(page.locator('#j-result, .gw-hatch')).toBeVisible();
  });

  test('gets the game page with its own sandbox and nothing from outside', async ({ page }) => {
    await page.goto('/ar/family');
    const lesson = page.locator('[data-station]:not([data-games="0"])');
    test.skip((await lesson.count()) === 0, 'no lesson with games in this data');
    await lesson.first().click();
    const play = page.getByRole('dialog').getByRole('button', { name: 'العب', exact: true });
    test.skip((await play.count()) === 0 || (await play.first().isDisabled()), 'no play time in this data');
    await play.first().click();
    const src = await page.locator('iframe.lms-game-frame').getAttribute('src');
    const answer = await page.request.get(src!);
    expect(answer.status()).toBe(200);
    const policy = answer.headers()['content-security-policy'] ?? '';
    expect(policy).toContain("connect-src 'none'");
    expect(policy).toContain('sandbox allow-scripts');
    expect(policy).toContain("frame-ancestors 'self'");
    expect((await page.request.get('/bff/games/not-a-version')).status()).toBe(404);
  });

  test('opens the week\'s chest when it is ready', async ({ page }) => {
    await page.goto('/ar/family');
    const open = page.getByRole('button', { name: 'افتح الصندوق' });
    test.skip((await open.count()) === 0, 'no chest to open in this data');
    await open.click();
    await expect(page.getByRole('status').filter({ hasText: /^خدت / })).toBeVisible();
  });
});

test.describe('a teen (Maryam)', () => {
  test.use({ storageState: 'e2e/.auth/teen.json' });

  test('has the calm journey: the level, the next lesson, the missions, the levels as a list', async ({ page }) => {
    const response = await page.goto('/ar/family');
    expect(response?.status()).toBe(200);
    await expect(page.locator('[data-stage="teen"]')).toBeVisible();
    await expect(page.locator('h1')).toHaveText('رحلتي');
    await fitsTheScreen(page);
    const tabs = page.getByRole('navigation', { name: 'القايمة' }).last();
    for (const name of ['رحلتي', 'حصصي', 'الواجبات', 'التقارير', 'المزيد']) await expect(tabs.getByRole('link', { name })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'المهمات' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'المستويات' })).toBeVisible();
    // A lesson opens its sheet, with its state.
    await page.locator('details[open] [data-station]').first().click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toBeHidden();
  });

  test('never gets an empty form for homework handed in by playing', async ({ page }) => {
    await page.goto('/ar/family/homework');
    const practice = page.getByRole('article').filter({ has: page.getByLabel('تدريب في اللعبة') });
    test.skip((await practice.count()) === 0, 'no practice homework in this data');
    await expect(practice.first().getByText('تدريب في اللعبة').first()).toBeVisible();
  });
});

test.describe('a guardian (Ziad\'s)', () => {
  test.use({ storageState: 'e2e/.auth/guardian.json' });

  test('looks at their child\'s journey, sets the play time, and cheers', async ({ page }) => {
    const response = await page.goto('/ar/family/journey');
    expect(response?.status()).toBe(200);
    await expect(page.locator('h1')).toHaveText(/^رحلة /);
    await fitsTheScreen(page);
    const tabs = page.getByRole('navigation', { name: 'القايمة' }).last();
    await expect(tabs.getByRole('link', { name: 'الرحلة' })).toHaveAttribute('aria-current', 'page');
    await expect(tabs.getByRole('link', { name: 'التقدم' })).toHaveCount(0);
    // The child opens the chest, never the guardian.
    await expect(page.getByRole('button', { name: 'افتح الصندوق' })).toHaveCount(0);
    await expect(page.getByText('مفيش شراء جوه الألعاب')).toBeVisible();

    const choice = page.getByRole('radio', { name: '15 د' });
    await choice.click();
    await expect(page.getByRole('status').filter({ hasText: 'اتحفظ.' })).toBeVisible();
    await expect(choice).toHaveAttribute('aria-checked', 'true');
    await page.getByRole('radio', { name: 'الأكاديمية' }).click();
    await expect(page.getByRole('radio', { name: 'الأكاديمية' })).toHaveAttribute('aria-checked', 'true');

    // The roadmap and the teacher's reports stay a click away.
    await expect(page.getByRole('link', { name: 'المنهج وتقارير المدرس' })).toBeVisible();
    // The child's album, to look at only.
    await page.goto('/ar/family/games/album');
    await expect(page.getByRole('button', { name: 'خليه صاحبك' })).toHaveCount(0);
  });

  test('finds the journey on the home page', async ({ page }) => {
    await page.goto('/ar/family');
    await page.getByRole('link', { name: /^رحلة / }).click();
    await expect(page).toHaveURL(/\/ar\/family\/journey$/);
  });
});

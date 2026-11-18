import { expect, fitsTheScreen, noRawKeys, test } from './helpers';

/*
 * The academy's page as blocks (docs/architecture/site-builder.md, 7.2):
 * the migrated demo page keeps today's sections, anchors and menu; the
 * builder's preview is never indexed, may be framed only as the proxy says,
 * and answers a stale link calmly. Each variant's drawing is checked in
 * components/site/page/render.test.tsx.
 */

test('the migrated demo page keeps its sections, anchors and menu', async ({ page }) => {
  await page.goto('/ar');
  await expect(page.locator('h1')).toHaveCount(1);
  for (const anchor of ['why', 'subjects', 'teachers', 'pricing', 'faq', 'join']) {
    await expect(page.locator(`#${anchor}`)).toHaveCount(1);
  }
  // Old links keep working: the menu and /ar#pricing land on the same blocks.
  const menu = page.getByRole('banner').getByRole('navigation');
  await expect(menu.getByRole('link', { name: 'الأسعار' })).toHaveAttribute('href', '#pricing');
  await page.goto('/ar#pricing');
  await expect(page.locator('#pricing')).toBeInViewport();
  // One structured-data script for the academy, and the FAQ's.
  const data = await page.locator('script[type="application/ld+json"]').allTextContents();
  expect(data.some((text) => text.includes('EducationalOrganization'))).toBe(true);
  await fitsTheScreen(page);
  await noRawKeys(page);
});

test('the questions open from the keyboard', async ({ page }) => {
  await page.goto('/ar#faq');
  const first = page.locator('#faq details').first();
  await first.locator('summary').focus();
  await page.keyboard.press('Enter');
  await expect(first).toHaveAttribute('open', '');
});

test('a stale or wrong preview link is answered calmly and never indexed', async ({ page }) => {
  const response = await page.goto('/ar/site-preview?t=not-a-real-token-at-all');
  expect(response?.status()).toBe(200);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText(/المعاينة/);
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);
  // Only the preview may be framed by the panels (proxy.ts); the rest keep SAMEORIGIN.
  expect(response?.headers()['x-frame-options']).toBeUndefined();
  expect(response?.headers()['content-security-policy']).toContain("frame-ancestors 'self'");
  const home = await page.goto('/ar');
  expect(home?.headers()['x-frame-options']).toBe('SAMEORIGIN');
});

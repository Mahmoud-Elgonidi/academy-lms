import { expect, test, type Page } from '@playwright/test';
import { PASSWORD, signInWithPassword } from './helpers';

// Rana (12) waits for her father's consent in the demo data; the test gives it, and can run again (it takes it back first).
const CHILD = process.env.E2E_CONSENT_CHILD ?? 'rana@demo.test';
const PARENT = process.env.E2E_CONSENT_GUARDIAN ?? 'rana.guardian@demo.test';
const NAME = 'رنا محمود';

const card = (page: Page) => page.locator('section').filter({ has: page.getByRole('heading', { name: NAME }) });

test('a child waits for their guardian, who agrees, and then the child signs in', async ({ page }) => {
  // A run before this one agreed: take it back, and Rana is signed out everywhere.
  await signInWithPassword(page, PARENT, PASSWORD);
  await expect(page).toHaveURL(/\/ar\/family/);
  await page.goto('/ar/family/privacy');
  const withdraw = card(page).getByRole('button', { name: 'اسحب الموافقة' });
  if (await withdraw.isVisible()) {
    page.once('dialog', (dialog) => dialog.accept());
    await withdraw.click();
    await expect(card(page).getByText('مستني موافقتك')).toBeVisible();
  }
  await page.context().clearCookies();

  // Rana herself is told why she cannot come in yet.
  await signInWithPassword(page, CHILD, PASSWORD);
  await expect(page.getByText('حسابك مستني موافقة ولي أمرك.', { exact: false })).toBeVisible();
  await expect(page).toHaveURL(/\/ar\/sign-in/);

  // Her father sees it on his home, reads, ticks and agrees.
  await signInWithPassword(page, PARENT, PASSWORD);
  await page.getByRole('link', { name: /رنا محمود مستني موافقتك/ }).click();
  await expect(page).toHaveURL(/\/ar\/family\/privacy$/);
  await expect(card(page).getByText('الحصص المباشرة')).toBeVisible();
  await card(page).getByRole('button', { name: 'موافق', exact: true }).click();
  await expect(card(page).getByText('علّم على "أنا ولي أمر" الأول.')).toBeVisible();
  await card(page).getByRole('checkbox').check();
  await card(page).getByRole('button', { name: 'موافق', exact: true }).click();
  await expect(card(page).getByText('موافق عليه')).toBeVisible();
  await expect(card(page).getByText(/^وافقت يوم /)).toBeVisible();
  await page.context().clearCookies();

  // Now she comes in.
  await signInWithPassword(page, CHILD, PASSWORD);
  await expect(page).toHaveURL(/\/ar\/family$/);
});

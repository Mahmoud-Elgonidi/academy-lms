import { PASSWORD, TEACHER, expect, signInWithPassword, test } from './helpers';

test('a page needs a session, and signing in comes back to it', async ({ page }) => {
  await page.goto('/ar/teach/week');
  await expect(page).toHaveURL(/\/ar\/sign-in\?next=%2Far%2Fteach%2Fweek/);
  await page.getByRole('tab', { name: 'بكلمة السر' }).click();
  await page.getByLabel('الإيميل أو الموبايل أو اسم المستخدم').fill(TEACHER);
  await page.getByLabel('كلمة السر').fill(PASSWORD);
  await page.getByRole('button', { name: 'ادخل' }).click();
  await expect(page).toHaveURL(/\/ar\/teach\/week$/);

  // The token never reaches the page's scripts.
  expect(await page.evaluate(() => document.cookie)).not.toContain('lms_session');
  const cookie = (await page.context().cookies()).find((c) => c.name === 'lms_session');
  expect(cookie?.httpOnly).toBe(true);

  // Signing out ends it here and on the API: the old token, put back, is refused and forgotten.
  await page.goto('/ar/teach/more');
  await page.getByRole('main').getByRole('button', { name: 'خروج' }).click();
  await expect(page).toHaveURL(/\/ar\/sign-in$/);
  expect((await page.context().cookies()).some((c) => c.name === 'lms_session')).toBe(false);
  await page.context().addCookies([cookie!]);
  await page.goto('/ar/teach');
  await expect(page).toHaveURL(/\/ar\/sign-in/);
  await expect(page.getByText('انتهت جلستك. ادخل تاني.')).toBeVisible();
  expect((await page.context().cookies()).some((c) => c.name === 'lms_session')).toBe(false);
});

test('a wrong password says so', async ({ page }) => {
  await signInWithPassword(page, `nobody-${Date.now()}@demo.test`, 'not-the-password');
  await expect(page.locator('form').getByRole('status').or(page.locator('form .text-danger'))).toBeVisible();
  await expect(page).toHaveURL(/\/ar\/sign-in/);
});

test('staff are sent to the back office', async ({ page }) => {
  await signInWithPassword(page, 'owner@demo.test', PASSWORD);
  await expect(page.getByText('حسابك للوحة الإدارة.')).toBeVisible();
  await expect(page.getByRole('link', { name: 'افتح لوحة الإدارة' })).toHaveAttribute('href', '/admin');
});

test('a code goes to the email or number, and a wrong one is refused', async ({ page }) => {
  await page.goto('/ar/sign-in');
  // The API answers the same whether or not the account exists.
  await page.getByLabel('الإيميل أو رقم الموبايل').fill(`e2e-${Date.now()}@demo.test`);
  await page.getByRole('button', { name: 'ابعتلي الكود' }).click();
  await expect(page.getByText(/بعتنا الكود على/)).toBeVisible();
  await page.getByLabel('الكود').fill('000000');
  await page.getByRole('button', { name: 'ادخل' }).click();
  await expect(page.locator('form .text-danger')).toBeVisible();
});

test('English reads left to right', async ({ page }) => {
  await page.goto('/en/sign-in');
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
  await expect(page.getByRole('button', { name: 'Send me a code' })).toBeVisible();
});

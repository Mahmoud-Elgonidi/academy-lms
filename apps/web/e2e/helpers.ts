import { expect, type Page } from '@playwright/test';

/** The demo data's teacher with history (Ziad's teacher), and the password every demo account has here. */
export const TEACHER = process.env.E2E_TEACHER ?? 'khaled@demo.test';
export const PASSWORD = process.env.E2E_PASSWORD ?? 'password';

/** Families in the demo data: Ziad's guardian (an overdue invoice to pay), Yousef (a kid) and Ziad (middle school) themselves. */
export const GUARDIAN = process.env.E2E_GUARDIAN ?? 'ziad.guardian@demo.test';
export const KID = process.env.E2E_KID ?? 'yousef@demo.test';
export const STUDENT = process.env.E2E_STUDENT ?? 'ziad@demo.test';

export async function signInWithPassword(page: Page, login: string, password: string) {
  await page.goto('/ar/sign-in');
  await page.getByRole('tab', { name: 'بكلمة السر' }).click();
  await page.getByLabel('الإيميل أو الموبايل أو اسم المستخدم').fill(login);
  await page.getByLabel('كلمة السر').fill(password);
  await page.getByRole('button', { name: 'ادخل' }).click();
}

/** No page is wider than the screen. */
export async function fitsTheScreen(page: Page) {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(0);
}

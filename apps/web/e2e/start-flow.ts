import type { BrowserContext, Page } from '@playwright/test';
import { serveAcademyHost, type TrialAfter } from './backend';
import { expect } from './helpers';

/*
 * The family's own start of a course (J3), the steps a spec walks through:
 * signed in with a token, from the trial's report to the flow, a teacher and
 * the week's times, the review, confirming with the guardian's consent, and
 * paying the invoice on the demo gateway back to the portal. Shared by
 * start-course.spec.ts and auto-renew.spec.ts.
 */

export async function signedIn(context: BrowserContext, token: string, baseURL: string | undefined) {
  await context.addCookies([{ name: 'lms_session', value: token, url: baseURL ?? 'http://127.0.0.1:3000', httpOnly: true, sameSite: 'Lax' }]);
}

/** From the report in the family's account to the flow, opened with the learner, subject and recommended plan. */
export async function fromReport(page: Page, family: TrialAfter) {
  await page.goto(`/ar/family/trials/${family.session}`);
  const start = page.locator('[data-start="flow"]');
  await expect(start).toHaveText(/ابدأ بهذه الباقة/);
  await start.click();
  await expect(page).toHaveURL(/\/ar\/family\/start\?.*plan=.*rec=/);
  await expect(page.getByRole('heading', { level: 1, name: 'ابدأ كورس ليان' })).toBeVisible();
  // The recommended plan is chosen; the teacher is next (the trial's teacher takes no new students in the demo).
  await expect(page.getByRole('heading', { level: 2, name: 'اختر المعلّم' })).toBeVisible();
}

/** A teacher, then the week's times: each day without a time gets its first one, until the plan's lessons a week are chosen. */
/** The step shown once its page came (the flow's panel is busy while the next step loads). */
export async function onStep(page: Page, title: string) {
  await expect(page.locator('section.st-panel[aria-busy="false"]').getByRole('heading', { level: 2, name: title })).toBeVisible();
}

export async function teacherAndTimes(page: Page) {
  const ids = await page.locator('[data-teacher]').evaluateAll((all) => all.map((el) => el.getAttribute('data-teacher') ?? ''));
  for (const id of ids) {
    await page.locator(`[data-teacher="${id}"]`).click();
    await expect(page).toHaveURL(new RegExp(`teacher=${id}.*step=times`));
    await onStep(page, 'اختر المواعيد الأسبوعية');
    await expect(page.locator('.st-day, [data-empty="times"]').first()).toBeVisible();
    // A teacher with no weekly time free for four weeks says so, with the way to another teacher.
    if (await page.locator('[data-empty="times"]').isVisible()) {
      await page.getByRole('button', { name: 'اختر معلّمًا آخر' }).click();
      await expect(page).not.toHaveURL(/step=times/);
      await onStep(page, 'اختر المعلّم');
      continue;
    }
    await choosePicks(page);
    return;
  }
  throw new Error('No teacher with a weekly time free for the plan.');
}

/** The week's times: each day without a time gets its first one, until the plan's lessons a week (one here) are chosen. */
export async function choosePicks(page: Page) {
  const days = page.locator('.st-day');
  await expect(days.first()).toBeVisible();
  for (let n = 0; n < (await days.count()) && (await page.locator('.st-time[aria-pressed="true"]').count()) < 1; n++) {
    const day = days.nth(n);
    if ((await day.locator('[aria-pressed="true"]').count()) === 0) await day.locator('.st-time').first().click();
  }
  await expect(page.getByText('اخترت 1 من 1')).toBeVisible();
}

/**
 * Another plan than the teacher recommended: one 30-minute lesson a week (so the demo's teachers keep room for every
 * run), chosen from the plans' step reached from the strip of steps; the recommended one is marked.
 */
export async function oneLessonAWeek(page: Page) {
  await page.getByRole('button', { name: 'ارجع إلى خطوة الباقة' }).click();
  await expect(page.getByRole('heading', { level: 2, name: 'اختر الباقة' })).toBeVisible();
  await expect(page.locator('[role="radio"][aria-checked="true"]').getByText('يقترحها المعلّم')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'حصص في مجموعة' })).toBeVisible();
  await page.getByRole('radio', { name: /^حصة واحدة في الأسبوع · 30 دقيقة/ }).first().click();
  await expect(page.getByRole('heading', { level: 2, name: 'اختر المعلّم' })).toBeVisible();
}

export async function toReview(page: Page) {
  await page.getByRole('button', { name: 'تابع' }).last().click();
  await expect(page.getByRole('heading', { level: 2, name: 'راجع قبل التأكيد' })).toBeVisible();
}

export async function confirm(page: Page, label = 'أكّد وابدأ الكورس') {
  await page.getByRole('button', { name: label }).last().click();
}

export async function agree(page: Page) {
  const consent = page.locator('[data-consent]');
  await expect(consent.getByText('خطوة أخيرة قبل البدء')).toBeVisible();
  await expect(consent.getByRole('heading', { name: 'موافقتك على حساب ليان' })).toBeFocused();
  // Not ticked: it says so, and sends nothing.
  await consent.getByRole('button', { name: 'أوافق وأكمل الحجز' }).click();
  await expect(consent.getByText('ضع علامة على الموافقة أولًا.')).toBeVisible();
  await consent.getByRole('checkbox').check();
  await consent.getByRole('button', { name: 'أوافق وأكمل الحجز' }).click();
}

/** Pays the invoice on screen on the demo gateway and comes back to the portal's /pay/return; its path. */
export async function payOnTheDemoGateway(page: Page, baseURL: string | undefined): Promise<string> {
  // The gateway's pretend page and the way back are on the academy's own host, served here as the server's Caddy would.
  const host = await serveAcademyHost(page, baseURL ?? 'http://127.0.0.1:3000');
  await page.locator('[data-pay]').getByRole('button', { name: /^ادفع / }).click();
  await page.waitForURL(/\/pay\/demo\//, { waitUntil: 'commit' });
  expect(new URL(page.url()).host).toBe(host);
  await page.getByRole('button', { name: 'ادفع', exact: true }).click();
  await page.waitForURL(/\/pay\/return\?invoice=/, { waitUntil: 'commit' });
  const back = new URL(page.url());
  expect(back.host).toBe(host);
  expect(back.pathname).toBe('/ar/pay/return');
  // The portal answers there, paid; the welcome of this start is in this tab's own storage, on the portal's address here.
  await expect(page.locator('[data-pay-state="paid"]')).toBeVisible();
  const path = `${back.pathname}${back.search}`;
  await page.goto(path);
  return path;
}

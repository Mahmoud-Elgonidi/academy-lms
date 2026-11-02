import type { BrowserContext, Page } from '@playwright/test';
import { API, coupon, endTestStarts, endTrialOffer, freeTestTrials, planPrice, removeTestHolidays, takeTrialSlot, testHoliday, trialAfter, type TrialAfter } from './backend';
import { expect, fitsTheScreen, noRawKeys, test } from './helpers';

/*
 * The family starts a course and pays on its own (docs/architecture/02,
 * section 27, J3): from the trial's report to a started course with the
 * offer on its first invoice, a time someone else took meanwhile chosen
 * again, the guardian's consent asked for and the start sent again by itself;
 * paying on the demo gateway and landing on /pay/return paid, with the
 * welcome; a price that changed meanwhile confirmed again; a coupon typed
 * and taken off (the invoice issued again each time); a start called off
 * before paying; and a child's own look refused calmly. Each family is new
 * to the run (E2eResetSeeder::trialAfter): a 9-year-old's Tajweed trial two
 * days ago, assessed, with an open 10% offer, the child's account waiting
 * for the guardian's consent. A week a time starts without (an academy
 * holiday two to three weeks ahead, E2eResetSeeder::testHoliday) is marked on the time,
 * listed in the review and in the welcome, in the API's words.
 */

test.describe.configure({ mode: 'serial' });

let first: TrialAfter;
let second: TrialAfter;
let secondInvoice = '';

test.beforeAll(() => {
  // Earlier runs' courses end and their families' trials to come are cancelled first: the demo's teachers keep their weekly room for this one.
  endTestStarts();
  freeTestTrials();
  first = trialAfter();
  second = trialAfter();
});

test.afterAll(() => {
  endTestStarts();
  removeTestHolidays();
});

test.beforeEach(async ({ page }) => {
  await page.setExtraHTTPHeaders({ 'X-Forwarded-For': `10.${Math.floor(Math.random() * 250)}.${Math.floor(Math.random() * 250)}.${Math.floor(Math.random() * 250) + 1}` });
});

async function signedIn(context: BrowserContext, token: string, baseURL: string | undefined) {
  await context.addCookies([{ name: 'lms_session', value: token, url: baseURL ?? 'http://127.0.0.1:3000', httpOnly: true, sameSite: 'Lax' }]);
}

/** From the report in the family's account to the flow, opened with the learner, subject and recommended plan. */
async function fromReport(page: Page, family: TrialAfter) {
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
async function onStep(page: Page, title: string) {
  await expect(page.locator('section.st-panel[aria-busy="false"]').getByRole('heading', { level: 2, name: title })).toBeVisible();
}

async function teacherAndTimes(page: Page) {
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
async function choosePicks(page: Page) {
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
async function oneLessonAWeek(page: Page) {
  await page.getByRole('button', { name: 'ارجع إلى خطوة الباقة' }).click();
  await expect(page.getByRole('heading', { level: 2, name: 'اختر الباقة' })).toBeVisible();
  await expect(page.locator('[role="radio"][aria-checked="true"]').getByText('يقترحها المعلّم')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'حصص في مجموعة' })).toBeVisible();
  await page.getByRole('radio', { name: /^حصة واحدة في الأسبوع · 30 دقيقة/ }).first().click();
  await expect(page.getByRole('heading', { level: 2, name: 'اختر المعلّم' })).toBeVisible();
}

async function toReview(page: Page) {
  await page.getByRole('button', { name: 'تابع' }).last().click();
  await expect(page.getByRole('heading', { level: 2, name: 'راجع قبل التأكيد' })).toBeVisible();
}

async function confirm(page: Page, label = 'أكّد وابدأ الكورس') {
  await page.getByRole('button', { name: label }).last().click();
}

async function agree(page: Page) {
  const consent = page.locator('[data-consent]');
  await expect(consent.getByText('خطوة أخيرة قبل البدء')).toBeVisible();
  await expect(consent.getByRole('heading', { name: 'موافقتك على حساب ليان' })).toBeFocused();
  // Not ticked: it says so, and sends nothing.
  await consent.getByRole('button', { name: 'أوافق وأكمل الحجز' }).click();
  await expect(consent.getByText('ضع علامة على الموافقة أولًا.')).toBeVisible();
  await consent.getByRole('checkbox').check();
  await consent.getByRole('button', { name: 'أوافق وأكمل الحجز' }).click();
}

test('from the trial report to a started course: the offer on, a time taken meanwhile chosen again, consent asked for once', async ({ page, context, baseURL }) => {
  await signedIn(context, first.guardian_token, baseURL);
  await fromReport(page, first);
  await expect(page.getByRole('list', { name: /الخطوة 3 من 5/ })).toBeVisible();
  await oneLessonAWeek(page);
  await teacherAndTimes(page);
  // The choices are in the address: a refresh keeps them.
  await page.reload();
  await expect(page.getByText('اخترت 1 من 1')).toBeVisible();
  await fitsTheScreen(page);

  // Someone else takes one of the chosen times while the family reviews.
  const taken = page.locator('.st-time[aria-pressed="true"]').first();
  const takenKey = (await taken.getAttribute('data-time')) ?? '';
  const takenAt = (await taken.getAttribute('data-first')) ?? '';
  await toReview(page);
  const money = page.locator('[data-money]');
  await expect(money.getByText(/كوبون TRIAL-/)).toBeVisible();
  await expect(money.getByText(/عرض ما بعد الحصة التجريبية مطبّق: خصم 10%/)).toBeVisible();
  await expect(page.getByText('كيف يتم الدفع')).toBeVisible();
  await expect(page.getByText(/ادفعها قبل أول حصة بـ\d+ ساعة على الأقل/)).toBeVisible();
  await expect(page.getByText(/حساب ليان ينتظر موافقتك/)).toBeVisible();
  await noRawKeys(page);
  expect(takeTrialSlot(takenAt.replace(/\.\d+Z$/, 'Z'))).toBeGreaterThan(0);

  // Consent first (asked for here, once), then the start goes again by itself and meets the taken time.
  await confirm(page);
  await agree(page);
  await expect(page.locator('[data-refusal="time_taken"]')).toBeVisible();
  await expect(page).toHaveURL(/step=times/);
  await expect(page.locator('.st-time[aria-pressed="true"]')).toHaveCount(0);
  await expect(page.locator(`.st-time[data-time="${takenKey}"]`)).toHaveCount(0);
  // The teacher may have no other time left: the way to another teacher is there.
  if (await page.locator('[data-empty="times"]').isVisible()) {
    await page.getByRole('button', { name: 'اختر معلّمًا آخر' }).click();
    await teacherAndTimes(page);
  } else {
    await expect(page.getByText('اخترت 0 من 1')).toBeVisible();
    await choosePicks(page);
  }
  await toReview(page);
  await confirm(page);

  // Started: the invoice with the offer on it, and the course's welcome.
  await expect(page).toHaveURL(/\/ar\/family\/invoices\/[0-9a-f-]{36}\?course=[0-9a-f-]{36}&started=1/);
  const welcome = page.locator('[data-welcome="waiting"]');
  await expect(welcome.getByRole('heading', { name: 'حُجز كورس تجويد لـليان' })).toBeVisible();
  await expect(welcome.locator('[data-first]')).not.toBeEmpty();
  await expect(welcome.locator('[data-offer-state="applied"]')).toBeVisible();
  await expect(welcome.locator('[data-pay-by]')).toBeVisible();
  await expect(page.locator('[data-lines]').getByText(/كوبون TRIAL-/)).toBeVisible();
  // The trial's offer stays on: nothing to type, said so.
  await expect(page.locator('[data-coupon="offer"]')).toBeVisible();
  await expect(page.getByRole('button', { name: 'ألغِ الكورس قبل الدفع' })).toBeVisible();
  await fitsTheScreen(page);
  await noRawKeys(page);

  // Paid on the demo gateway in the same tab: back on /pay/return, paid, with the course's welcome.
  returnPath = await payOnTheDemoGateway(page, baseURL);
  await expect(page.locator('[data-pay-state="paid"]')).toBeVisible();
  await expect(page.getByRole('heading', { level: 1, name: 'وصلنا الدفع، شكرًا!' })).toBeVisible();
  const paid = page.locator('[data-welcome="paid"]');
  await expect(paid.getByRole('heading', { name: 'مرحبًا بـليان في كورس تجويد!' })).toBeVisible();
  await expect(paid.locator('[data-first]')).not.toBeEmpty();
  await expect(paid.getByRole('link', { name: 'الحصص ورابط الدخول' })).toHaveAttribute('href', '/ar/family/lessons');
  await fitsTheScreen(page);
  await noRawKeys(page);
});

let returnPath = '';

/** Pays the invoice on screen on the demo gateway and comes back to the portal's /pay/return; its path. */
async function payOnTheDemoGateway(page: Page, baseURL: string | undefined): Promise<string> {
  await page.locator('[data-pay]').getByRole('button', { name: /^ادفع / }).click();
  await page.waitForURL(/\/pay\/demo\//, { waitUntil: 'commit' });
  // On a laptop the gateway's pretend page is the API's own (on a server it is the same host).
  const gateway = new URL(page.url());
  if (!gateway.href.startsWith(API)) await page.goto(`${API}${gateway.pathname}${gateway.search}`);
  await page.getByRole('button', { name: 'ادفع', exact: true }).click();
  await page.waitForURL(/\/pay\/return\?invoice=/, { waitUntil: 'commit' });
  const back = new URL(page.url());
  expect(back.pathname).toBe('/ar/pay/return');
  const path = `${back.pathname}${back.search}`;
  if (!back.href.startsWith(baseURL ?? '')) await page.goto(path);
  return path;
}

test('back from the gateway in another browser or the app: paid, the first lesson from the lessons, the way back to the app', async ({ page, context, baseURL }) => {
  await signedIn(context, first.guardian_token, baseURL);
  await page.goto(returnPath);
  await expect(page.locator('[data-pay-state="paid"]')).toBeVisible();
  // Without the start in this tab, the welcome says the first lesson from the lessons themselves.
  const welcome = page.locator('[data-welcome="plain"]');
  await expect(welcome.getByRole('heading', { name: 'اكتمل التسجيل' })).toBeVisible();
  await expect(welcome.locator('[data-first]')).toContainText('أول حصة:');
  await fitsTheScreen(page);
  await noRawKeys(page);

  // From the app: back to the app first, even without a session here.
  const app = await page.context().browser()!.newContext({ baseURL, locale: 'ar-EG', timezoneId: 'Africa/Cairo' });
  const phone = await app.newPage();
  await phone.goto(`${returnPath}&from=app`);
  await expect(phone.getByRole('heading', { name: 'عد إلى التطبيق' })).toBeVisible();
  await expect(phone.getByRole('link', { name: 'افتح التطبيق' })).toHaveAttribute('href', /^academyapp:\/\/app\/open\/invoice\/[0-9a-f-]{36}$/);
  await app.close();
});

test('the price changed meanwhile: the new price shows, confirmed again, and the course starts at it', async ({ page, context, baseURL }) => {
  await signedIn(context, second.guardian_token, baseURL);
  endTrialOffer(second.session);
  await fromReport(page, second);
  await oneLessonAWeek(page);
  await teacherAndTimes(page);
  await toReview(page);
  await expect(page.getByText('انتهى عرض الحصة التجريبية، فالسعر هنا هو السعر المعتاد.')).toBeVisible();
  const plan = new URL(page.url()).searchParams.get('plan')!;
  const old = planPrice(plan, 'EGP', 23500);
  try {
    await confirm(page);
    const refusal = page.locator('[data-refusal="price"]');
    await expect(refusal).toBeVisible();
    await expect(refusal).toContainText('235');
    await expect(page.locator('[data-money]')).toContainText('235');
    await confirm(page, 'أكّد بالسعر الجديد');
    await agree(page);
    await expect(page).toHaveURL(/\/ar\/family\/invoices\/[0-9a-f-]{36}\?course=/);
    await expect(page.locator('[data-total]')).toContainText('235');
    secondInvoice = new URL(page.url()).pathname + new URL(page.url()).search;
  } finally {
    planPrice(plan, 'EGP', old);
  }
});

test('a coupon before paying: a wrong code refused in words, a right one issues the invoice again, and comes off again', async ({ page, context, baseURL }) => {
  const code = coupon('E2E-WELCOME', 15);
  await signedIn(context, second.guardian_token, baseURL);
  await page.goto(secondInvoice);
  const before = new URL(page.url()).pathname;
  const box = page.locator('[data-coupon="entry"]');
  await box.getByRole('button', { name: 'طبّق' }).click();
  await expect(box.getByText('اكتب رمز الكوبون أولًا.')).toBeVisible();
  await box.getByLabel('معك كوبون خصم؟').fill('NOPE-NOT-HERE');
  await box.getByRole('button', { name: 'طبّق' }).click();
  await expect(box.getByRole('alert')).toContainText('NOPE-NOT-HERE');

  await box.getByLabel('معك كوبون خصم؟').fill(code);
  await box.getByRole('button', { name: 'طبّق' }).click();
  await expect(page.getByText('طُبّق الكوبون، وهذه فاتورتك الجديدة بالخصم.')).toBeVisible();
  expect(new URL(page.url()).pathname).not.toBe(before);
  await expect(page.locator('[data-lines]').getByText(new RegExp(code))).toBeVisible();
  // The course stays named on the new invoice, so it can still be called off.
  await expect(page).toHaveURL(/course=[0-9a-f-]{36}/);

  const applied = new URL(page.url()).pathname;
  await page.locator('[data-coupon="on"]').getByRole('button', { name: 'أزل الكوبون' }).click();
  await expect(page.getByText('أُزيل الكوبون، وهذه فاتورتك الجديدة.')).toBeVisible();
  expect(new URL(page.url()).pathname).not.toBe(applied);
  await expect(page.locator('[data-coupon="entry"]')).toBeVisible();
  secondInvoice = new URL(page.url()).pathname + new URL(page.url()).search;
  await fitsTheScreen(page);
});

test('a start called off before paying: what happens said first, then done, and the subject open again', async ({ page, context, baseURL }) => {
  await signedIn(context, second.guardian_token, baseURL);
  await page.goto(secondInvoice);
  await page.getByRole('button', { name: 'ألغِ الكورس قبل الدفع' }).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog.getByText('تُلغى الفاتورة، ولا يُدفع شيء.')).toBeVisible();
  await dialog.getByRole('button', { name: 'أبقِ الكورس' }).click();
  await expect(dialog).toBeHidden();
  await page.getByRole('button', { name: 'ألغِ الكورس قبل الدفع' }).click();
  await page.getByRole('dialog').getByRole('button', { name: 'نعم، ألغِ الكورس' }).click();
  await expect(page.locator('[data-cancelled]')).toContainText('أُلغي الكورس ولم يُدفع شيء');
  await expect(page).toHaveURL(/cancelled=1/);
  // The invoice went with it: nothing to pay here any more.
  await expect(page.locator('[data-pay]')).toHaveCount(0);
  await page.locator('[data-cancelled]').getByRole('link', { name: 'ابدأ كورسًا جديدًا' }).click();
  await expect(page.locator('[data-subject="01a1416a-b328-709a-93ec-d39831e03623"], [role="radio"]').first()).toBeVisible();
  await expect(page.locator('[data-subject-taken]')).toHaveCount(0);
});

test('a course already in the subject shows as such, with the way to it instead of starting again', async ({ page, context, baseURL }) => {
  await signedIn(context, first.guardian_token, baseURL);
  await page.goto('/ar/family/start');
  const taken = page.locator('[data-subject-taken]');
  await expect(taken).toHaveCount(1);
  await expect(taken).toContainText('تجويد');
  await expect(taken.getByRole('link', { name: 'افتح الحصص' })).toBeVisible();
  // The classic home and the menu lead here.
  await page.goto('/ar/family');
  await expect(page.locator('[data-start-card]')).toBeVisible();
});

test('a child\'s own look is told calmly that a guardian starts the course', async ({ page, context, baseURL }) => {
  await signedIn(context, first.learner_token, baseURL);
  await page.goto('/ar/family/start');
  const closed = page.locator('[data-closed]');
  await expect(closed.getByRole('heading', { name: 'يبدأ وليّ الأمر الكورس' })).toBeVisible();
  await expect(closed).toContainText('وليّ الأمر');
  await expect(page.locator('[data-plan], [data-teacher]')).toHaveCount(0);
  await noRawKeys(page);
});

test('a holiday in the first weeks: the time is marked and says which day has no lesson, the review and the welcome list it', async ({ page, context, baseURL }) => {
  const family = trialAfter();
  // Two to three weeks ahead falls in every weekly time's first four weeks: that weekday's times skip that week, and stay offered.
  const day = testHoliday(15);
  expect(day).toMatch(/^\d{4}-\d{2}-\d{2}$/);
  try {
    await signedIn(context, family.guardian_token, baseURL);
    await fromReport(page, family);
    await oneLessonAWeek(page);
    // The first teacher with a time on the holiday's weekday.
    const ids = await page.locator('[data-teacher]').evaluateAll((all) => all.map((el) => el.getAttribute('data-teacher') ?? ''));
    const note = page.locator('.st-day [data-skip]').filter({ hasText: 'إجازة الأكاديمية' }).first();
    for (const id of ids) {
      await page.locator(`[data-teacher="${id}"]`).click();
      await onStep(page, 'اختر المواعيد الأسبوعية');
      await expect(page.locator('.st-day, [data-empty="times"]').first()).toBeVisible();
      if ((await note.count()) > 0) break;
      await page.getByRole('button', { name: 'ارجع إلى خطوة المعلّم' }).click();
      await onStep(page, 'اختر المعلّم');
    }
    // The sentence sits under the day's times, and the time it belongs to carries a marker and is described by it.
    const sentence = /^لا حصة يوم .+: إجازة الأكاديمية$/;
    await expect(note).toHaveText(sentence);
    const marked = page.locator(`.st-time[data-skips="true"][aria-describedby~="${await note.getAttribute('id')}"]`).first();
    await expect(marked).toBeVisible();
    await expect(marked).toHaveAccessibleDescription(sentence);
    // Chosen (the suggested time let go first: one lesson a week): its first lesson is a real one, never the holiday's.
    if ((await marked.getAttribute('aria-pressed')) !== 'true') {
      const chosen = page.locator('.st-time[aria-pressed="true"]');
      if ((await chosen.count()) > 0) await chosen.first().click();
      await expect(page.getByText('اخترت 0 من 1')).toBeVisible();
      await marked.click();
    }
    await expect(marked).toHaveAttribute('aria-pressed', 'true');
    await expect(page.getByText('اخترت 1 من 1')).toBeVisible();
    expect((await marked.getAttribute('data-first'))?.slice(0, 10)).not.toBe(day);
    await fitsTheScreen(page);

    await toReview(page);
    await expect(page.locator('[data-review-skips] [data-skip="holiday"]')).toHaveText(sentence);
    await confirm(page);
    await agree(page);
    await expect(page).toHaveURL(/\/ar\/family\/invoices\/[0-9a-f-]{36}\?course=/);
    const welcome = page.locator('[data-welcome="waiting"]');
    await expect(welcome.locator('[data-welcome-skips] [data-skip="holiday"]')).toHaveText(sentence);
    await fitsTheScreen(page);
    await noRawKeys(page);
  } finally {
    removeTestHolidays();
  }
});

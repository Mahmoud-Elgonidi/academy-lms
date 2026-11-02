import type { BrowserContext, Locator, Page } from '@playwright/test';
import { endTrialOffer, trialAfter, type TrialAfter } from './backend';
import { expect, fitsTheScreen, noRawKeys, test } from './helpers';

/*
 * After the free trial (docs/architecture/02, section 27, J2): the family's
 * report from its own link (the report card, the plan with its price, the
 * offer and its countdown, the way to start), readable without script; the
 * offer ending while the page is open and after it ended; the free second
 * trial with another teacher booked from the report; "book again" after a
 * trial the child missed; and the same report in the family's account (the
 * list and a report, the second trial booked there), with the child's own
 * look reading it without prices or the offer. Each family is new to the run
 * (E2eResetSeeder::trialAfter, through artisan tinker): an assessed trial two
 * days ago with an open offer, and a missed one.
 */

test.describe.configure({ mode: 'serial' });

let family: TrialAfter;

test.beforeAll(() => {
  family = trialAfter();
});

test.beforeEach(async ({ page }) => {
  await page.setExtraHTTPHeaders({ 'X-Forwarded-For': `10.${Math.floor(Math.random() * 250)}.${Math.floor(Math.random() * 250)}.${Math.floor(Math.random() * 250) + 1}` });
});

/** Signs this browser in with a session token (the portal's cookie), as a passwordless family account would after its code. */
async function signedIn(context: BrowserContext, token: string, baseURL: string | undefined) {
  await context.addCookies([{ name: 'lms_session', value: token, url: baseURL ?? 'http://127.0.0.1:3000', httpOnly: true, sameSite: 'Lax' }]);
}

/** The n-th day with times in a picker, its first time. */
async function pickTime(scope: Locator, day = 0): Promise<string> {
  const days = scope.locator('.lt-day');
  await expect(days.first()).toBeVisible();
  await days.nth(Math.min(day, (await days.count()) - 1)).click();
  const time = scope.locator('.lt-time').first();
  await time.click();
  return (await time.locator('input').getAttribute('value')) ?? '';
}

async function readsTheReport(page: Page) {
  await expect(page.getByRole('heading', { level: 1, name: 'ليان في تجويد' })).toBeVisible();
  await expect(page.getByText('تقرير الحصة التجريبية', { exact: true })).toBeVisible();
  await expect(page.getByText('المستوى الأول: تقرأ قراءة صحيحة')).toBeVisible();
  const strengths = page.locator('.lr-list[data-tone="good"] li');
  await expect(strengths).toHaveText(['تقرأ بصوت واضح وثقة.', 'تحفظ بسرعة، وتسأل حين لا تفهم.']);
  await expect(page.locator('.lr-list[data-tone="grow"] li')).toHaveCount(2);
  await expect(page.locator('.lr-quote blockquote')).toContainText('سعدت بالتعرف إلى ليان');
  // Never what the teacher wrote for the academy alone.
  await expect(page.getByText('تحتاج معلّمة صبورة')).toHaveCount(0);
}

test('the report from the family\'s link: the report card, the plan and its price, the offer with its time left, and the way to start', async ({ page }) => {
  await page.goto(family.report);
  await readsTheReport(page);

  const plan = page.locator('.lr-plan');
  await expect(plan.getByRole('heading', { name: 'الباقة التي يقترحها المعلّم' })).toBeVisible();
  await expect(plan.getByText('حصتان في الأسبوع', { exact: true })).toBeVisible();
  await expect(plan.getByText('30 دقيقة للحصة')).toBeVisible();
  await expect(plan.getByText('حصص فردية')).toBeVisible();
  // The offer: its percent, the price after it, the usual one struck through, the saving, and one lesson's share.
  await expect(plan.getByText('خصم 10%')).toBeVisible();
  await expect(plan.locator('.lr-price-offer b')).toBeVisible();
  await expect(plan.locator('.lr-was s')).toBeVisible();
  await expect(plan.getByText(/^توفّر /)).toBeVisible();
  await expect(plan.getByText(/للحصة الواحدة/).first()).toBeVisible();
  // The time left: days, hours and minutes (a day and more), and its end on the family's clock.
  const timer = page.getByRole('timer');
  await expect(timer).toContainText('ينتهي العرض بعد');
  await expect(timer.locator('li')).toHaveCount(3);
  await expect(timer.locator('small').first()).toHaveText(/يوم|أيام/);
  await expect(timer).toContainText(/حتى .+ · /);

  // Start: the family's own flow (J3), opened with the learner, the subject and the recommended plan (signing in first);
  // or a WhatsApp chat with the academy that names the learner, the subject, the plan and the offer.
  const start = page.locator('[data-start]');
  await expect(start).toHaveText(/ابدأ بهذه الباقة/);
  await expect(start).toHaveAttribute('data-start', 'flow');
  const href = (await start.getAttribute('href')) ?? '';
  expect(href).toMatch(/^\/ar\/family\/start\?learner=[\w-]+&subject=[\w-]+&plan=[\w-]+&rec=[\w-]+&trial=/);
  expect(href).toContain(`trial=${family.session}`);
  const chat = (await page.locator('.lr-start a.lt-link').getAttribute('href')) ?? '';
  expect(chat).toMatch(/^https:\/\/wa\.me\/\d+\?text=/);
  const words = decodeURIComponent(chat.split('text=')[1] ?? '');
  expect(words).toContain('ليان');
  expect(words).toContain('خصم 10%');

  await expect(page.getByRole('heading', { name: 'لم يناسبك المعلّم؟' })).toBeVisible();
  await fitsTheScreen(page);
  await noRawKeys(page);
});

test('the report reads whole without script, the countdown as the server wrote it', async ({ browser, baseURL }) => {
  const context = await browser.newContext({ javaScriptEnabled: false, baseURL, locale: 'ar-EG', timezoneId: 'Africa/Cairo' });
  const page = await context.newPage();
  await page.goto(family.report);
  await readsTheReport(page);
  await expect(page.getByRole('timer').locator('b').first()).toHaveText(/^\d{2}$/);
  await expect(page.locator('.lr-price-offer b')).toBeVisible();
  await context.close();
});

test('in English the report reads left to right, in the same words', async ({ page }) => {
  await page.goto(family.report_en);
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
  await expect(page.getByText('Free trial report', { exact: true })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'The plan the teacher recommends' })).toBeVisible();
  await expect(page.getByText('10% off')).toBeVisible();
  await expect(page.getByRole('timer')).toContainText('The offer ends in');
  await expect(page.locator('[data-start]')).toHaveText(/Start with this plan/);
  await noRawKeys(page);
  await fitsTheScreen(page);
});

test('the offer ends while the page is open: the countdown gives way to the usual price, with no reload', async ({ page }) => {
  await page.goto(family.report);
  const ends = Date.parse((await page.getByRole('timer').getAttribute('data-ends')) ?? '');
  expect(ends).toBeGreaterThan(Date.now());
  // The same page, its clock five seconds before the end.
  await page.clock.install({ time: ends - 5_000 });
  await page.goto(family.report);
  const timer = page.getByRole('timer');
  await expect(timer).toHaveAttribute('data-urgency', 'last');
  await expect(timer.locator('small').last()).toHaveText(/ثانية|ثوانٍ|ثانيتان/);
  await expect(timer).toContainText('الساعة الأخيرة');
  await page.clock.runFor(7_000);
  await expect(page.getByRole('timer')).toHaveCount(0);
  await expect(page.getByText('انتهى العرض', { exact: true })).toBeVisible();
  await expect(page.locator('.lr-was')).toHaveCount(0);
  await expect(page.locator('.lr-plan .lr-price b')).toBeVisible();
});

test('the free second trial with another teacher books from the report, and then says it was used', async ({ page }) => {
  await page.goto(family.report);
  await expect(page.getByText(/متاحة حتى /)).toBeVisible();
  await page.getByRole('button', { name: 'اختر موعدًا مع معلّم آخر' }).click();
  await expect(page.getByRole('heading', { name: 'اختر موعد الحصة الثانية' })).toBeFocused();
  const panel = page.locator('.lr-next-card');
  await expect(panel.getByText(/المواعيد بتوقيتك/)).toBeVisible();
  // "Not now" folds it back.
  await panel.getByRole('button', { name: 'ليس الآن' }).click();
  await page.getByRole('button', { name: 'اختر موعدًا مع معلّم آخر' }).click();
  await pickTime(page.locator('.lr-next-card'), 1);
  await page.getByRole('button', { name: 'احجز هذا الموعد' }).click();
  await expect(page.getByRole('heading', { name: 'حُجزت الحصة الثانية لـليان' })).toBeVisible();
  await expect(page.getByText('مع معلّم آخر، والحصة مجانية.')).toBeVisible();
  // Never with the first teacher.
  await expect(page.locator('.lt-booked .lt-facts')).not.toContainText('أ. سارة');
  await fitsTheScreen(page);

  await page.reload();
  await expect(page.getByRole('heading', { name: 'الحصة الثانية غير متاحة الآن' })).toBeVisible();
  await expect(page.getByText(/حجزتم الحصة التجريبية الثانية في هذه المادة من قبل/)).toBeVisible();
});

test('once the offer ended, the report shows the usual price and says so calmly', async ({ page }) => {
  endTrialOffer(family.session);
  await page.goto(family.report);
  await expect(page.getByText('انتهى العرض', { exact: true })).toBeVisible();
  await expect(page.getByRole('timer')).toHaveCount(0);
  await expect(page.getByText('خصم 10%')).toHaveCount(0);
  await expect(page.locator('.lr-plan .lr-price b')).toBeVisible();
  // Starting still names the plan, now without the offer.
  const words = decodeURIComponent(((await page.locator('.lr-start a.lt-link').getAttribute('href')) ?? '').split('text=')[1] ?? '');
  expect(words).toContain('ليان');
  expect(words).not.toContain('خصم');
  await fitsTheScreen(page);
});

test('"book again" after a missed trial: what happened, the times, booked once, then the link says so', async ({ page }) => {
  await page.goto(family.rebook);
  await expect(page.getByRole('heading', { level: 1, name: 'احجز موعدًا آخر لـآدم' })).toBeVisible();
  await expect(page.getByText(/افتقدنا آدم في حصة/)).toBeVisible();
  await expect(page.getByText('لا حاجة إلى رمز')).toBeVisible();
  await pickTime(page.locator('.lr-next-card'), 1);
  await page.getByRole('button', { name: 'احجز هذا الموعد' }).click();
  await expect(page.getByRole('heading', { name: 'حُجز الموعد الجديد لـآدم' })).toBeVisible();
  await fitsTheScreen(page);
  await noRawKeys(page);

  await page.goto(family.rebook);
  await expect(page.getByRole('heading', { name: 'انتهت مدة هذا الرابط' })).toBeVisible();
  await expect(page.getByText(/حجزتم موعدًا جديدًا بالفعل/)).toBeVisible();
});

test('a link that names no report says so, with the way to the account', async ({ page }) => {
  await page.goto('/ar/trial/report/bm90LWEtcmVhbC10b2tlbi1hdC1hbGwuMTc5NjE0NTc3NA.AAAAAAAAAAAAAAAAAAAAAAAA');
  await expect(page.getByRole('heading', { name: 'لم نجد هذا التقرير' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'ادخل إلى حسابك' })).toHaveAttribute('href', '/ar/sign-in');
});

test.describe('in the family\'s account', () => {
  let own: TrialAfter;
  test.beforeAll(() => {
    own = trialAfter();
  });

  test('the guardian finds the trial and its report, and books the second trial there', async ({ page, context, baseURL }) => {
    await signedIn(context, own.guardian_token, baseURL);
    await page.goto('/ar/family/more');
    await page.getByRole('link', { name: 'الحصص التجريبية' }).click();
    await expect(page).toHaveURL(/\/ar\/family\/trials$/);
    const item = page.locator(`[data-trial="${own.session}"]`);
    await expect(item.getByRole('heading', { name: 'تجويد' })).toBeVisible();
    await expect(item.getByText('التقرير جاهز')).toBeVisible();
    await expect(item.getByRole('link', { name: 'احجز حصة ثانية مع معلّم آخر' })).toBeVisible();
    await fitsTheScreen(page);
    await noRawKeys(page);

    await item.getByRole('link', { name: 'اقرأ التقرير' }).click();
    await expect(page).toHaveURL(new RegExp(`/ar/family/trials/${own.session}$`));
    await readsTheReport(page);
    await expect(page.getByText('خصم 10%')).toBeVisible();
    await expect(page.getByRole('timer')).toBeVisible();
    await fitsTheScreen(page);

    await page.getByRole('button', { name: 'اختر موعدًا مع معلّم آخر' }).click();
    await pickTime(page.locator('.lr-next-card'), 2);
    await page.getByRole('button', { name: 'احجز هذا الموعد' }).click();
    await expect(page.getByRole('heading', { name: 'حُجزت الحصة الثانية لـليان' })).toBeVisible();
    await page.getByRole('link', { name: 'كل الحصص التجريبية' }).last().click();
    // The new one on the list, to come.
    await expect(page.getByText('الحصة الثانية', { exact: true })).toBeVisible();
    await expect(page.getByText('قادمة', { exact: true })).toBeVisible();
  });

  test('the child reads the same report in their own look, without prices, the offer or booking', async ({ page, context, baseURL }) => {
    await signedIn(context, own.learner_token, baseURL);
    await page.goto('/ar/family/more');
    await page.getByRole('link', { name: 'حصتي التجريبية' }).click();
    await page.getByRole('link', { name: 'اقرأ التقرير' }).click();
    await expect(page.getByText('تقرير حصتك التجريبية')).toBeVisible();
    await expect(page.getByText('ما تجيده')).toBeVisible();
    await expect(page.getByText('رسالة من معلّمك')).toBeVisible();
    for (const word of ['الباقة التي يقترحها المعلّم', 'خصم', 'ينتهي العرض', 'لم يناسبك المعلّم؟', 'ابدأ بهذه الباقة']) {
      await expect(page.getByText(word)).toHaveCount(0);
    }
    await expect(page.getByRole('timer')).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'احجز حصة ثانية مع معلّم آخر' })).toHaveCount(0);
    await fitsTheScreen(page);
    await noRawKeys(page);
  });
});

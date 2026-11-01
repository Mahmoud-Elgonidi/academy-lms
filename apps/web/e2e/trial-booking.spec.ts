import type { Locator, Page } from '@playwright/test';
import { phoneCode, takeTrialSlot, trialBooking, trialLinks } from './backend';
import { expect, fitsTheScreen, noRawKeys, test } from './helpers';

/*
 * A family asks for and books its own free trial (docs/architecture/02,
 * section 27) on the demo academy's page: a request the academy answers
 * while its staff book trials; booking a time with the code sent to the
 * number (the code read from the API's log, as WhatsApp's log driver writes
 * it); a time someone took between seeing and confirming it; the "pick a
 * time" link of the instant reply; and the trial's own links to change its
 * time and to cancel it. The global setup puts the academy back to families
 * booking. Each test uses a number of its own (a code a minute per number),
 * and comes from an address of its own, as different families would.
 */

const FORM_READY_MS = 3_500;

/** A mobile number no earlier run used. */
const freshPhone = () => `+9665${String(Date.now()).slice(-8)}`;

test.describe.configure({ mode: 'serial' });

test.beforeEach(async ({ page }) => {
  await page.setExtraHTTPHeaders({ 'X-Forwarded-For': `10.${Math.floor(Math.random() * 250)}.${Math.floor(Math.random() * 250)}.${Math.floor(Math.random() * 250) + 1}` });
});

async function openForm(page: Page, locale: 'ar' | 'en' = 'ar'): Promise<Locator> {
  await page.goto(`/${locale}#join`);
  const form = page.locator('#ls-panel-trial');
  await expect(form).toBeVisible();
  // The page's form token is good three seconds after the page gave it.
  await page.waitForTimeout(FORM_READY_MS);
  return form;
}

/** Step 1: a child (or the learner themself) and a subject (by its place in the list). */
async function whoIsLearning(form: Locator, options: { child?: string; subject?: number; help?: boolean } = {}) {
  if (options.child) {
    await form.getByLabel('اسمك (ولي الأمر)').fill('سارة من الاختبار');
    await form.getByLabel('اسم الطفل').fill(options.child);
    await form.getByLabel('عمر الطفل').selectOption('9');
  } else {
    await form.getByLabel(/لي أنا/).check();
    await form.getByLabel('اسمك').fill('متعلّم من الاختبار');
  }
  if (options.help) await form.getByLabel('ساعدوني في الاختيار').check();
  else await form.locator('.lt-subjects .ls-chip').nth(options.subject ?? 3).click();
  await form.getByRole('button', { name: 'التالي' }).click();
}

/** The time step: the n-th day with times, its first time; returns the time's instant (UTC). */
async function pickTime(scope: Locator, day = 0): Promise<string> {
  const days = scope.locator('.lt-day');
  await expect(days.first()).toBeVisible();
  await days.nth(Math.min(day, (await days.count()) - 1)).click();
  const time = scope.locator('.lt-time').first();
  await time.click();
  return (await time.locator('input').getAttribute('value')) ?? '';
}

test('while the academy books trials itself, the family sends a request and is told when to expect an answer', async ({ page }) => {
  trialBooking('staff');
  try {
    const form = await openForm(page);
    // Three steps: no time to pick.
    await expect(form.locator('.lt-progress li')).toHaveCount(3);
    // Sent empty, the first step says what is missing, next to each field.
    await form.getByRole('button', { name: 'التالي' }).click();
    await expect(form.getByRole('alert')).toHaveText('راجع الخانات المحددة أدناه.');
    await expect(form.getByText('اكتب اسمك (حرفين على الأقل).')).toBeVisible();
    await whoIsLearning(form, { child: 'سلمى' });
    await expect(form.getByRole('heading', { name: 'متى يناسبكم؟' })).toBeFocused();
    await form.getByLabel('الخميس').check();
    await form.getByRole('button', { name: /مساءً/ }).click();
    await form.getByLabel('معلّمة', { exact: true }).check();
    await form.getByRole('button', { name: 'التالي' }).click();
    await expect(form.getByRole('heading', { name: 'كيف نتواصل معك؟' })).toBeVisible();
    // Back keeps what was typed.
    await form.getByRole('button', { name: 'رجوع' }).click();
    await expect(form.getByRole('button', { name: /مساءً/ })).toHaveAttribute('aria-pressed', 'true');
    await form.getByRole('button', { name: 'التالي' }).click();
    await form.getByLabel('الجوال (واتساب)').fill('050');
    await form.getByRole('button', { name: 'أرسل الطلب' }).click();
    await expect(form.getByText('اكتب الرقم مع رمز الدولة')).toBeVisible();
    await form.getByLabel('الجوال (واتساب)').fill(freshPhone());
    await form.getByRole('button', { name: 'أرسل الطلب' }).click();
    await expect(form.getByRole('heading', { name: 'وصلنا طلبك' })).toBeVisible();
    await expect(form.getByText('سنرد عليك خلال 4 ساعات.')).toBeVisible();
    await fitsTheScreen(page);
    await noRawKeys(page);
  } finally {
    trialBooking('family');
  }
});

test('a family books its own trial with the code sent to its number, and can add it to its calendar', async ({ page }) => {
  const form = await openForm(page);
  await expect(form.locator('.lt-progress li')).toHaveCount(4);
  await whoIsLearning(form, { child: 'يارا' });
  await form.getByRole('button', { name: /مساءً/ }).click();
  await form.getByRole('button', { name: 'التالي' }).click();
  await expect(form.getByRole('heading', { name: 'اختر موعد الحصة المجانية' })).toBeFocused();
  await expect(form.getByText(/المواعيد بتوقيتك/)).toBeVisible();
  await pickTime(form);
  await form.getByRole('button', { name: 'تابع بهذا الموعد' }).click();

  const phone = freshPhone();
  await form.getByLabel('الجوال (واتساب)').fill(phone);
  await form.getByRole('button', { name: 'أرسل لي الرمز' }).click();
  await expect(form.getByText(/أرسلنا رمزًا إلى/)).toBeVisible();
  await expect(form.getByText(/يمكنك طلب رمز جديد بعد/)).toBeVisible();
  // A wrong code is refused with the tries left, and nothing is booked.
  await form.getByLabel('الرمز المكوّن من 6 أرقام').fill('000000');
  await form.getByRole('button', { name: 'أكّد الحجز' }).click();
  await expect(form.getByText(/الرمز غير صحيح/)).toBeVisible();
  await form.getByLabel('الرمز المكوّن من 6 أرقام').fill(await phoneCode(phone));
  await form.getByRole('button', { name: 'أكّد الحجز' }).click();

  await expect(form.getByRole('heading', { name: 'حُجزت الحصة المجانية لـيارا' })).toBeVisible();
  await expect(form.getByText(/^مع أ\. /)).toBeVisible();
  await expect(form.getByText('نذكّرك على واتساب قبل الحصة بيوم.')).toBeVisible();
  const ics = await form.getByRole('link', { name: 'أضفها إلى تقويمي' }).getAttribute('href');
  const file = await page.request.get(ics!);
  expect(file.headers()['content-type']).toContain('text/calendar');
  expect(await file.text()).toMatch(/BEGIN:VCALENDAR[\s\S]*DTSTART:\d{8}T\d{6}Z/);
  await fitsTheScreen(page);

  // Another child: the parent's name and number stay, the child's go.
  await form.getByRole('button', { name: 'احجز لطفل آخر' }).click();
  await expect(form.getByLabel('اسمك (ولي الأمر)')).toHaveValue('سارة من الاختبار');
  await expect(form.getByLabel('اسم الطفل')).toHaveValue('');
});

test('a time someone took meanwhile is refused kindly, the list is fresh, and the code still works', async ({ page }) => {
  const form = await openForm(page);
  await whoIsLearning(form, { subject: 3 });
  await form.getByRole('button', { name: 'التالي' }).click();
  const taken = await pickTime(form, 1);
  await form.getByRole('button', { name: 'تابع بهذا الموعد' }).click();
  const phone = freshPhone();
  await form.getByLabel('الجوال (واتساب)').fill(phone);
  await form.getByRole('button', { name: 'أرسل لي الرمز' }).click();
  await form.getByLabel('الرمز المكوّن من 6 أرقام').fill(await phoneCode(phone));

  // Another family takes it before this one confirms.
  expect(takeTrialSlot(taken)).toBeGreaterThan(0);
  await form.getByRole('button', { name: 'أكّد الحجز' }).click();
  await expect(form.getByRole('heading', { name: 'اختر موعد الحصة المجانية' })).toBeVisible();
  await expect(form.getByRole('alert')).toContainText('حجز أحدهم هذا الموعد');
  await expect(form.locator(`.lt-time input[value="${taken}"]`)).toHaveCount(0);

  await pickTime(form, 2);
  await form.getByRole('button', { name: 'تابع بهذا الموعد' }).click();
  await expect(form.getByText('رمزك ما زال صالحًا')).toBeVisible();
  await form.getByRole('button', { name: 'أكّد الحجز' }).click();
  await expect(form.getByRole('heading', { name: 'حُجزت حصتك المجانية' })).toBeVisible();
});

test.describe('the family\'s own links', () => {
  const phone = freshPhone();

  test('"pick a time" books from the instant reply\'s link, with no code, once', async ({ page }) => {
    const form = await openForm(page);
    await whoIsLearning(form, { child: 'آدم' });
    await form.getByRole('button', { name: 'التالي' }).click();
    // They would rather be called: a request, and the reply brings the link.
    await form.getByRole('button', { name: /أفضّل أن تتواصلوا معي/ }).click();
    await form.getByLabel('الجوال (واتساب)').fill(phone);
    await form.getByRole('button', { name: 'أرسل الطلب' }).click();
    await expect(form.getByRole('heading', { name: 'وصلنا طلبك' })).toBeVisible();
    await expect(form.getByText('وفيها رابط تختار منه الموعد بنفسك إن شئت.')).toBeVisible();

    const { pick } = trialLinks(phone);
    await page.goto(pick);
    await expect(page.getByRole('heading', { name: 'اختر موعد حصتك المجانية' })).toBeVisible();
    await expect(page.getByText(/لـآدم ·/)).toBeVisible();
    await expect(page.getByText('لا حاجة إلى رمز')).toBeVisible();
    // Two days on at least, so the next test can still move it.
    await pickTime(page.locator('main'), 2);
    await page.getByRole('button', { name: 'احجز هذا الموعد' }).click();
    await expect(page.getByRole('heading', { name: 'حُجزت الحصة المجانية لـآدم' })).toBeVisible();
    await fitsTheScreen(page);
    await noRawKeys(page);

    // Booked once: the link has done its work.
    await page.goto(pick);
    await expect(page.getByRole('heading', { name: 'هذا الرابط لم يعد يعمل' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'اطلب حصة مجانية' })).toHaveAttribute('href', '/ar#join');
  });

  test('the trial\'s own links change its time and then cancel it, with a reason', async ({ page }) => {
    const { change, cancel } = trialLinks(phone);
    expect(change && cancel).toBeTruthy();
    await page.goto(change!);
    await expect(page.getByRole('heading', { name: 'غيّر موعد حصتك المجانية' })).toBeVisible();
    await expect(page.getByText('بقي تغييران')).toBeVisible();
    await expect(page.getByText(/يمكنك تغييره حتى/)).toBeVisible();
    await pickTime(page.locator('main'), 1);
    await page.getByRole('button', { name: 'انقلها إلى هذا الموعد' }).click();
    await expect(page.getByRole('heading', { name: 'تم: لحصتك موعدها الجديد' })).toBeVisible();
    await expect(page.getByText(/يمكنك تغييره مرة أخرى/)).toBeVisible();
    await fitsTheScreen(page);

    await page.goto(cancel!);
    await expect(page.getByRole('heading', { name: 'هل تريد إلغاء الحصة المجانية؟' })).toBeVisible();
    // Keeping it changes nothing.
    await page.getByRole('button', { name: 'أبقِ الحصة' }).click();
    await expect(page.getByRole('heading', { name: 'حصتك باقية كما هي' })).toBeVisible();
    await page.goto(cancel!);
    await page.getByLabel('ما السبب؟ (اختياري)').fill('سفر مفاجئ');
    await page.getByRole('button', { name: 'نعم، ألغِ الحصة' }).click();
    await expect(page.getByRole('heading', { name: 'أُلغيت الحصة' })).toBeVisible();

    // Cancelled: both links say so calmly, with the ways on.
    for (const link of [change!, cancel!]) {
      await page.goto(link);
      await expect(page.getByRole('heading', { name: 'هذا الرابط لم يعد يعمل' })).toBeVisible();
      await expect(page.getByRole('link', { name: 'راسلنا على واتساب' })).toHaveAttribute('href', /^https:\/\/wa\.me\//);
    }
  });
});

test('in English the steps read left to right, in the same words', async ({ page }) => {
  const form = await openForm(page, 'en');
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
  await expect(form.getByRole('heading', { name: 'Who is the lesson for?' })).toBeVisible();
  await form.getByLabel(/^Me/).check();
  await form.getByLabel('Your name').fill('A learner from the test');
  await form.locator('.lt-subjects .ls-chip').nth(3).click();
  await form.getByRole('button', { name: 'Next' }).click();
  await expect(form.getByRole('heading', { name: 'When suits you?' })).toBeFocused();
  await form.getByRole('button', { name: 'Next' }).click();
  await expect(form.getByRole('heading', { name: 'Pick a time for the free lesson' })).toBeVisible();
  await expect(form.locator('.lt-time').first()).toBeVisible();
  await noRawKeys(page);
  await fitsTheScreen(page);
});

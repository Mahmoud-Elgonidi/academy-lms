import type { Browser, Page } from '@playwright/test';
import { fixtures } from './backend';
import { PASSWORD, expect, fitsTheScreen, noRawKeys, signInWithPassword, test } from './helpers';

/**
 * The Quran program (docs/architecture/02, section 26) on the demo's Quran
 * course (E2eResetSeeder's `quran` fixtures): its teacher records a lesson
 * from the portion's panel and reads the student's plan, the family sees
 * where the learner is and the certificate, and anyone checks the
 * certificate by its code without signing in.
 */

async function signedIn(browser: Browser, login: string): Promise<Page> {
  const context = await browser.newContext({ locale: 'ar-EG', timezoneId: 'Africa/Cairo', viewport: { width: 412, height: 915 }, isMobile: true, hasTouch: true });
  const page = await context.newPage();
  await signInWithPassword(page, login, PASSWORD);
  await expect(page).not.toHaveURL(/sign-in/);
  return page;
}

test.describe('the Quran', () => {
  test('the teacher records a lesson from its portion, and reads the plan', async ({ browser }) => {
    const { quran } = fixtures();
    const page = await signedIn(browser, quran.teacher);

    // The lesson's room leads to the Quran panel.
    await page.goto(`/ar/teach/lessons/${quran.lesson_id}`);
    await page.locator('[data-quran-panel]').click();
    await expect(page).toHaveURL(new RegExp(`/ar/teach/lessons/${quran.lesson_id}/quran$`));
    await expect(page.locator('h1')).toHaveText('حصة القرآن');
    await expect(page.locator('[data-part="new"]').first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'افتح المصحف المشترك' })).toHaveAttribute('href', `/ar/teach/lessons/${quran.lesson_id}`);
    await fitsTheScreen(page);
    await noRawKeys(page);

    // Two mistakes of the first kind in the new part, then one taken back; the grade the mistakes give.
    const part = page.locator('[data-record-part="new"]').first();
    const kind = part.locator('[data-mistake]').first();
    await kind.getByRole('button', { name: /^زد / }).click();
    await kind.getByRole('button', { name: /^زد / }).click();
    await kind.getByRole('button', { name: /^أنقص / }).click();
    await expect(kind.locator('output')).toHaveText('1');
    // A grade of one's own needs a note: refused without it.
    await part.getByRole('radio', { name: 'ممتاز' }).click();
    await page.getByRole('button', { name: 'سجّل الحصة' }).click();
    await expect(page.getByText('اكتب سبب التقدير الذي اخترته.')).toBeVisible();
    await part.getByRole('radio', { name: 'حسب الأخطاء' }).click();
    await page.getByRole('button', { name: 'سجّل الحصة' }).click();
    await expect(page.getByText('سُجّلت هذه الحصة.')).toBeVisible();
    await expect(page.locator('[data-result]').first()).toBeVisible();

    // The student's plan: where they are, the next portion, and their pace (saved as it is).
    await page.goto(`/ar/teach/students/${quran.enrollment_id}`);
    await page.locator('[data-quran-plan]').click();
    await expect(page.locator('h1')).toHaveText('خطة القرآن');
    await expect(page.locator('[data-position]')).toBeVisible();
    await page.getByRole('button', { name: 'احفظ الوتيرة' }).click();
    await expect(page.getByText('تم الحفظ.')).toBeVisible();
    await fitsTheScreen(page);

    // The examiner's queue opens, booked or not.
    await page.goto('/ar/teach/exams');
    await expect(page.locator('h1')).toHaveText('اختبارات القرآن');
    await noRawKeys(page);
    await page.context().close();
  });

  test('the family sees where the learner is, the thirty parts and the certificate', async ({ browser }) => {
    const { quran } = fixtures();
    const page = await signedIn(browser, quran.guardian);
    await page.goto('/ar/family/quran');
    await expect(page.locator('[data-position]').first()).toBeVisible();
    await expect(page.locator('[data-progress]').first()).toBeVisible();
    await expect(page.locator('[data-juz-strip] [data-juz]')).toHaveCount(30);
    const certificate = page.locator(`[data-certificate="${quran.certificate_number}"]`);
    await expect(certificate).toBeVisible();
    await expect(certificate.getByRole('link', { name: /صفحة التحقق/ })).toHaveAttribute('href', `/ar/certificates/${quran.certificate_code}`);
    await fitsTheScreen(page);
    await noRawKeys(page);
    // The hifz map is under the Quran's place in the menu.
    await page.goto('/ar/family/hifz');
    await expect(page.locator('h1')).toBeVisible();
    await page.context().close();

    // The child in the kids' look: stars and simple words, no percent.
    const kid = await signedIn(browser, quran.student);
    await kid.goto('/ar/family/quran');
    await expect(kid.locator('[data-stage="kids"]')).toBeVisible();
    await expect(kid.locator('[data-stars]').first()).toBeVisible();
    await expect(kid.locator('[data-progress]')).toHaveCount(0);
    await fitsTheScreen(kid);
    await noRawKeys(kid);
    await kid.context().close();
  });

  test('anyone checks a certificate by its code, and an unknown code is not found', async ({ browser }) => {
    const { quran } = fixtures();
    const context = await browser.newContext({ locale: 'ar-EG' });
    const page = await context.newPage();
    // Any case and dashes, as typed from the paper.
    const response = await page.goto(`/ar/certificates/${quran.certificate_code.toLowerCase()}`);
    expect(response?.status()).toBe(200);
    await expect(page.getByText('شهادة صحيحة')).toBeVisible();
    await expect(page.locator(`[data-certificate="${quran.certificate_number}"]`)).toBeVisible();
    await fitsTheScreen(page);
    await noRawKeys(page);

    const missing = await page.goto('/ar/certificates/AAAA-BBBB-CCCC-DDDD');
    expect(missing?.status()).toBe(404);
    await expect(page.getByRole('heading', { name: 'لم نجد هذه الشهادة' })).toBeVisible();
    await context.close();
  });
});

import { fixtures } from './backend';
import { expect, fitsTheScreen, noRawKeys, test } from './helpers';

/*
 * Group lessons (docs/architecture/02, section 25) on the portal, with the
 * demo group E2eResetSeeder sets up (`fixtures().group`): a family sees the
 * group badge, its seats and classmates, and on a group lesson "I won't
 * attend" (its own seat excused, the lesson going on) instead of cancelling;
 * the teacher sees the roster and writes the class's report once with a row
 * per student; the academy's page shows its group plan.
 */

test.describe('the family of a student in a group', () => {
  test.use({ storageState: 'e2e/.auth/group-guardian.json' });

  test('sees the group badge with its seats and classmates, and the course as a group', async ({ page }) => {
    const { lesson_id } = fixtures().group;
    await page.goto('/ar/family/lessons');
    const card = page.locator(`article[data-lesson="${lesson_id}"]`);
    await expect(card).toHaveAttribute('data-format', 'group');
    const badge = card.locator('span[data-format="group"]');
    await expect(badge).toContainText('جماعية');
    await expect(badge.locator('[data-seat="taken"]').first()).toBeVisible();
    await expect(badge).toContainText(/\d+\/\d+/);
    // The child's own seat ("مقعدك 2 من 4").
    await expect(card.getByText(/^مقعدك \d+ من \d+$/)).toBeVisible();
    // With whom: the classmates' first names after how many ("مع زميلين: ليلى، عمر").
    await expect(card.getByText(/^مع .+: .+/)).toBeVisible();
    // Never cancelled from here: only "I won't attend".
    await expect(card.getByRole('button', { name: 'إلغاء الحصة' })).toHaveCount(0);
    await expect(card.getByRole('button', { name: 'لن أحضر هذه الحصة' })).toBeVisible();
    // Private lessons, if any, stay plain: no group look on them.
    await expect(page.locator('article[data-format="private"] span[data-format="group"]')).toHaveCount(0);
    await fitsTheScreen(page);
    await noRawKeys(page);

    // The course reads as a group with its seats and the child's own, as the API says of the course.
    await page.goto('/ar/family/progress');
    const course = page.locator('a[data-format="group"]').first();
    await expect(course).toBeVisible();
    await expect(course.locator('span[data-format="group"]')).toContainText(/\d+\/\d+/);
    await expect(course).toContainText(/مجموعة .+ · مقعدك \d+ من \d+/);
    // Money shows the plan as group, with the seat.
    await page.goto('/ar/family/money');
    const balance = page.locator('li[data-format="group"]').first();
    await expect(balance).toContainText('باقة جماعية');
    await expect(balance.getByText(/^مقعدك \d+ من \d+$/)).toBeVisible();
  });

  test('says they will not attend a group lesson: the seat is excused, the lesson goes on', async ({ page }) => {
    const { lesson_id } = fixtures().group;
    await page.goto('/ar/family/lessons');
    const card = page.locator(`article[data-lesson="${lesson_id}"]`);
    await card.getByRole('button', { name: 'لن أحضر هذه الحصة' }).click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    // What it means for the class and for the balance, in words that never say "cancel".
    await expect(dialog).toContainText('تستمر لزملائك في موعدها');
    await expect(dialog.getByText('ما يحدث للرصيد:')).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'نعم، ألغِها' })).toHaveCount(0);
    await expect(dialog.getByRole('button', { name: 'سأحضر' })).toBeVisible();
    // A reason first, then the seat is excused.
    await dialog.getByRole('button', { name: 'نعم، لن أحضر' }).click();
    await expect(dialog.getByText('اختر سببًا أولًا.')).toBeVisible();
    await dialog.getByLabel('السبب').selectOption({ index: 1 });
    await dialog.getByRole('button', { name: 'نعم، لن أحضر' }).click();
    await expect(dialog).toBeHidden();

    // The lesson is still there, not cancelled, with this seat excused and nothing more to excuse.
    await page.reload();
    const after = page.locator(`article[data-lesson="${lesson_id}"]`);
    await expect(after).toHaveAttribute('data-format', 'group');
    await expect(after.locator('.line-through')).toHaveCount(0);
    await expect(after.getByText(/^مقعدك: /)).toBeVisible();
    await expect(after.getByRole('button', { name: 'لن أحضر هذه الحصة' })).toHaveCount(0);
  });
});

test.describe('a student in a group, in their own look', () => {
  test.use({ storageState: 'e2e/.auth/group-student.json' });

  test('sees the group lesson with its badge, and never cancels it', async ({ page }) => {
    const { lesson_id } = fixtures().group;
    await page.goto('/ar/family/lessons');
    const card = page.locator(`article[data-lesson="${lesson_id}"]`);
    await expect(card).toHaveAttribute('data-format', 'group');
    await expect(card.locator('span[data-format="group"]')).toContainText('جماعية');
    // The kids' look: how many classmates, never their names.
    await expect(card.getByText(/^مع /)).toBeVisible();
    await expect(card.getByText(/^مع .+: .+/)).toHaveCount(0);
    await expect(card.getByRole('button', { name: 'إلغاء الحصة' })).toHaveCount(0);
    await fitsTheScreen(page);
    await noRawKeys(page);
  });
});

test.describe('the group’s teacher', () => {
  test.use({ storageState: 'e2e/.auth/group-teacher.json' });

  test('sees the group and its roster on the lesson card and the lesson page', async ({ page }) => {
    const { lesson_id } = fixtures().group;
    await page.goto(`/ar/teach/lessons/${lesson_id}`);
    await expect(page.locator('h1')).toContainText('مجموعة');
    const header = page.locator('header').filter({ has: page.locator('h1') });
    await expect(header.locator('span[data-format="group"]')).toContainText(/\d+\/\d+/);
    await fitsTheScreen(page);

    // The week (or the next one) has the group's lessons with who is in them.
    await page.goto('/ar/teach/week');
    let card = page.locator('article[data-format="group"]').first();
    if ((await card.count()) === 0) {
      await page.getByRole('link', { name: 'الأسبوع القادم' }).click();
      card = page.locator('article[data-format="group"]').first();
    }
    await expect(card).toBeVisible();
    await expect(card.getByRole('list', { name: 'الطلاب في الحصة' }).getByRole('listitem').first()).toBeVisible();
    await expect(card.locator('span[data-format="group"] [data-seat="taken"]').first()).toBeVisible();
    await noRawKeys(page);
  });

  test('writes the class’s report once, with a row for each student', async ({ page }) => {
    await page.goto(`/ar/teach/reports/${fixtures().group.report_id}`);
    await expect(page.locator('h1')).toHaveText('تقرير الحصة الجماعية');
    await expect(page.getByText('للمجموعة كلها.')).toBeVisible();
    const rows = page.locator('li[data-student]');
    expect(await rows.count()).toBeGreaterThan(1);
    await fitsTheScreen(page);
    await noRawKeys(page);

    // The shared part: what happened with each lesson of the plan, and the subject's own questions.
    const plans = page.getByRole('radiogroup').filter({ has: page.getByRole('radio', { name: 'أتقنه' }) });
    for (let i = 0; i < (await plans.count()); i++) await plans.nth(i).getByRole('radio', { name: 'تدرّب عليه' }).click();
    if ((await page.getByRole('heading', { name: 'أسئلة الأكاديمية' }).count()) > 0) {
      await page.getByRole('radiogroup', { name: 'الإعراب' }).getByRole('radio', { name: '4 من 5' }).click();
      await page.getByLabel('الكلمات الجديدة').fill('6');
    }

    // Sent with the rows empty: each row says what it is missing.
    await page.getByRole('button', { name: /أرسل التقرير|حدّث التقرير/ }).click();
    await expect(page.getByText(/ينقص/)).toBeVisible();
    await expect(page.locator('li[data-student][data-invalid="true"]').first()).toBeVisible();
    await expect(page).toHaveURL(/\/ar\/teach\/reports\/[\w-]+$/);

    // Each student's own row: ratings and a note for one who came, the absence note for one who did not.
    for (let i = 0; i < (await rows.count()); i++) {
      const row = rows.nth(i);
      if ((await row.getAttribute('data-kind')) === 'lesson') {
        const scales = row.getByRole('radiogroup');
        if ((await scales.count()) === 0) continue; // reviewed by a supervisor: it stays
        await scales.nth(0).getByRole('radio', { name: '4 من 5' }).click();
        await scales.nth(1).getByRole('radio', { name: '5 من 5' }).click();
        await row.getByRole('textbox').fill('مشاركة جميلة اليوم.');
      } else {
        const note = row.getByRole('textbox');
        if ((await note.count()) > 0) await note.fill('لم يحضر، نراه في الحصة القادمة.');
      }
    }
    await expect(page.locator('li[data-student][data-invalid="true"]')).toHaveCount(0);
    await page.getByLabel('أعطِ واجبًا').check();
    await page.getByLabel('الواجب', { exact: true }).fill('اقرأ النص وأجب عن الأسئلة');
    await page.getByRole('button', { name: /أرسل التقرير|حدّث التقرير/ }).click();

    await expect(page).toHaveURL(/\/ar\/teach\/reports\?sent=1$/);
    await expect(page.getByText('أُرسل التقرير.')).toBeVisible();
  });
});

test.describe('the academy’s page', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('shows a group plan with its badge and how many students a lesson takes', async ({ page }) => {
    await page.goto('/ar');
    const plan = page.locator('#pricing article[data-format="group"]').first();
    await expect(plan).toBeVisible();
    await expect(plan.locator('span[data-format="group"]')).toContainText('جماعية');
    await expect(plan.getByText(/^حتى .+ في الحصة$/)).toBeVisible();
    // Beside private plans, those say so plainly.
    const privatePlan = page.locator('#pricing article[data-format="private"]').first();
    if ((await privatePlan.count()) > 0) await expect(privatePlan.locator('span[data-format="private"]')).toHaveText('فردية');
    await fitsTheScreen(page);
    await noRawKeys(page);
  });
});

import { expect, fitsTheScreen, test } from './helpers';

// Ziad's guardian, in the classic look: lessons, money and cheering on.
test.use({ storageState: 'e2e/.auth/guardian.json' });

test('every family page opens, fits a phone, and reads right to left', async ({ page }) => {
  for (const path of ['/ar/family', '/ar/family/lessons', '/ar/family/lessons?when=past', '/ar/family/progress', '/ar/family/homework', '/ar/family/money', '/ar/family/challenges', '/ar/family/recordings', '/ar/family/notifications', '/ar/family/more']) {
    const response = await page.goto(path);
    expect(response?.status(), path).toBe(200);
    await expect(page.locator('h1').first(), path).toBeVisible();
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await fitsTheScreen(page);
  }
  // The classic tab bar has the money, not the challenges.
  const tabs = page.getByRole('navigation', { name: 'القايمة' }).last();
  await expect(tabs.getByRole('link', { name: 'الفلوس' })).toBeVisible();
  await tabs.getByRole('link', { name: 'الحصص' }).click();
  await expect(page).toHaveURL(/\/ar\/family\/lessons$/);
});

test('a guardian sees what cancelling would do, and may keep the lesson', async ({ page }) => {
  await page.goto('/ar/family/lessons');
  const cancel = page.getByRole('button', { name: 'إلغاء الحصة' });
  test.skip((await cancel.count()) === 0, 'no lesson to cancel in this data');
  await cancel.first().click();
  const dialog = page.getByRole('dialog');
  await expect(dialog).toBeVisible();
  await expect(dialog.getByText('حسب سياسة الأكاديمية:')).toBeVisible();
  // Confirming without a reason asks for one.
  await dialog.getByRole('button', { name: 'أيوه، ألغيها' }).click();
  await expect(dialog.getByText('اختار سبب الأول.')).toBeVisible();
  await dialog.getByRole('button', { name: 'لأ، خليها' }).click();
  await expect(dialog).toBeHidden();
});

test('a guardian rates a lesson that took place', async ({ page }) => {
  await page.goto('/ar/family/lessons?when=past');
  const rate = page.getByRole('button', { name: 'قيّم الحصة' });
  test.skip((await rate.count()) === 0, 'no lesson to rate in this data');
  await rate.first().click();
  const dialog = page.getByRole('dialog');
  await dialog.getByRole('radio', { name: '4 من 5' }).click();
  await expect(dialog.getByText('إيه اللي عجبكم؟')).toBeVisible();
  await dialog.getByRole('button', { name: 'ابعت التقييم' }).click();
  await expect(dialog).toBeHidden();
  await expect(page.getByText('شكرًا! وصل تقييمك.')).toBeVisible();
});

test('what is owed is paid on the academy\'s payment page', async ({ page }) => {
  await page.goto('/ar/family/money');
  await expect(page.getByText('الفواتير', { exact: true })).toBeVisible();
  const pay = page.getByRole('button', { name: /^ادفع / });
  test.skip((await pay.count()) === 0, 'nothing owed in this data');
  await pay.first().click();
  // The academy's own gateway page (the demo gateway here); the invoice is paid only when it says so.
  await page.waitForURL(/\/pay\//);
});

test('a guardian who paid by transfer sends the receipt, and the invoice says it waits for the academy', async ({ page }) => {
  await page.goto('/ar/family/money');
  const paidByHand = page.getByRole('button', { name: 'دفعت بالتحويل' });
  test.skip((await paidByHand.count()) === 0, 'no invoice takes a proof in this data');
  await paidByHand.first().click();
  const form = page.locator('form').filter({ hasText: 'ابعت إثبات الدفع' });
  await expect(form.getByText('مصرف الراجحي', { exact: false })).toBeVisible();

  // No receipt yet: nothing is sent.
  await form.getByRole('button', { name: 'ابعت الإثبات' }).click();
  await expect(form.getByText('اختار الإيصال الأول.')).toBeVisible();

  await form.getByText('تحويل بنكي', { exact: true }).click();
  await form.getByLabel('رقم التحويل').fill('TRF-E2E-1');
  // A tiny picture of our own as the receipt (1×1 PNG).
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64');
  await form.locator('input[type="file"]').setInputFiles({ name: 'receipt.png', mimeType: 'image/png', buffer: png });
  await form.getByRole('button', { name: 'ابعت الإثبات' }).click();

  await expect(page.getByText('الإثبات اتبعت. هنقولك أول ما الأكاديمية تأكّده.')).toBeVisible();
  await expect(page.getByText(/ومستني الأكاديمية/).first()).toBeVisible();
});

test('a guardian cheers their child on', async ({ page }) => {
  await page.goto('/ar/family/challenges');
  await expect(page.getByRole('heading', { name: /^شجّع / })).toBeVisible();
  test.skip(await page.getByText('خلّصت تشجيعات النهارده').isVisible(), 'no cheers left today');
  await page.getByRole('button', { name: 'نجمة' }).click();
  await expect(page.getByText(/^التشجيع وصل لـ /)).toBeVisible();
  // Only the student collects rewards.
  await expect(page.getByRole('button', { name: /^استلم / })).toHaveCount(0);
});

test('a guardian agrees to their child\'s lessons being recorded, and takes it back', async ({ page }) => {
  await page.goto('/ar/family/recordings');
  const agree = page.getByRole('button', { name: 'موافق', exact: true });
  const withdraw = page.getByRole('button', { name: 'اسحب الموافقة' });
  await expect(agree.or(withdraw)).toBeVisible();
  if (await withdraw.isVisible()) {
    page.once('dialog', (d) => d.accept());
    await withdraw.click();
  }

  await page.getByRole('checkbox').check();
  await agree.click();
  await expect(withdraw).toBeVisible();
  await expect(page.getByText('مش موافق')).toHaveCount(0);

  page.once('dialog', (d) => d.accept());
  await withdraw.click();
  await expect(agree).toBeVisible();
});

test('a guardian puts their child\'s details right, and asks the academy for what cannot be changed here', async ({ page }) => {
  await page.goto('/ar/family/my-data');
  await fitsTheScreen(page);
  // The child's card: the facts, corrected on the spot.
  const child = page.getByRole('article').filter({ has: page.getByText('من ولادك') }).first();
  const fix = child.locator('[data-part="fix"]');
  // A grade other than the one on file, so it changes on every run.
  const grade = (await fix.locator('dt', { hasText: 'الصف' }).locator('xpath=following-sibling::dd[1]').innerText()) === 'جامعة' ? 'تالتة ثانوي' : 'جامعة';
  await fix.getByRole('button', { name: 'صحّح' }).click();
  await fix.getByLabel('الصف').selectOption({ label: grade });
  await fix.getByRole('button', { name: 'احفظ' }).click();
  await expect(fix.getByText('اتحفظ.')).toBeVisible();
  await expect(fix.getByText(grade, { exact: true })).toBeVisible();

  // The rest is a request the academy answers; it can be taken back while it waits.
  await fix.getByRole('button', { name: 'اطلب تصحيح' }).click();
  await fix.getByLabel('إيه اللي محتاج يتصلح').fill('تاريخ الميلاد المتسجل غلط: الصح 2013.');
  await fix.getByRole('button', { name: 'ابعت الطلب' }).click();
  await expect(fix.getByText(/اتطلب: الأكاديمية هترد قبل/)).toBeVisible();
  await fix.getByRole('button', { name: 'الغي الطلب' }).click();
  await expect(fix.getByRole('button', { name: 'اطلب تصحيح' })).toBeVisible();
});

test('a guardian\'s account shows the email and number, and a new one waits for its code', async ({ page }) => {
  await page.goto('/ar/family/account');
  await fitsTheScreen(page);
  const email = page.locator('li[data-kind="email"]');
  await expect(email).toContainText('@');
  await email.getByRole('button', { name: 'غيّر الإيميل' }).click();
  await page.getByLabel('الإيميل الجديد').fill('not an email');
  await page.getByRole('button', { name: 'ابعت كود' }).click();
  await expect(page.getByText('اكتب إيميل صحيح.')).toBeVisible();
  await page.getByRole('button', { name: 'رجوع' }).click();
});

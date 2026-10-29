import { API, approveProof } from './backend';
import { expect, fitsTheScreen, noRawKeys, test } from './helpers';
import { familyPages } from './pages';

// Ziad's guardian, in the classic look: lessons, money and cheering on.
test.use({ storageState: 'e2e/.auth/guardian.json' });

const PAGES = [...familyPages('classic', { money: true, guardian: true }), '/family/lessons?when=past', '/family/progress'];

test('every family page opens, fits a phone, and reads right to left', async ({ page }) => {
  test.setTimeout(120_000);
  for (const path of PAGES.map((p) => `/ar${p}`)) {
    const response = await page.goto(path);
    expect(response?.status(), path).toBe(200);
    await expect(page.locator('h1').first(), path).toBeVisible();
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await fitsTheScreen(page);
    await noRawKeys(page);
  }
  // The classic tab bar has the money and the journey, not the progress.
  await page.goto('/ar/family');
  const tabs = page.getByRole('navigation', { name: 'القائمة' }).last();
  await expect(tabs.getByRole('link', { name: 'المدفوعات' })).toBeVisible();
  await expect(tabs.getByRole('link', { name: 'الرحلة' })).toBeVisible();
  await tabs.getByRole('link', { name: 'الحصص' }).click();
  await expect(page).toHaveURL(/\/ar\/family\/lessons$/);
});

test('every family page reads in English, left to right', async ({ page }) => {
  test.setTimeout(120_000);
  for (const path of PAGES.map((p) => `/en${p}`)) {
    const response = await page.goto(path);
    expect(response?.status(), path).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    await expect(page.locator('h1').first(), path).toBeVisible();
    await fitsTheScreen(page);
    await noRawKeys(page);
  }
  await page.goto('/en/family');
  const tabs = page.getByRole('navigation', { name: 'Menu' }).last();
  await tabs.getByRole('link', { name: 'Payments' }).click();
  await expect(page).toHaveURL(/\/en\/family\/money$/);
  await expect(page.getByText('Invoices', { exact: true })).toBeVisible();
});

test('a guardian sees what cancelling would do, and may keep the lesson', async ({ page }) => {
  await page.goto('/ar/family/lessons');
  await page.getByRole('button', { name: 'إلغاء الحصة' }).first().click();
  const dialog = page.getByRole('dialog');
  await expect(dialog).toBeVisible();
  await expect(dialog.getByText('حسب سياسة الأكاديمية:')).toBeVisible();
  // Confirming without a reason asks for one.
  await dialog.getByRole('button', { name: 'نعم، ألغِها' }).click();
  await expect(dialog.getByText('اختر سببًا أولًا.')).toBeVisible();
  await dialog.getByRole('button', { name: 'لا، أبقِها' }).click();
  await expect(dialog).toBeHidden();
});

test('a guardian cancels a lesson after reading what the policy does with it', async ({ page }) => {
  await page.goto('/ar/family/lessons');
  // The last lesson ahead: far enough to be in good time.
  const lesson = page.getByRole('article').filter({ has: page.getByRole('button', { name: 'إلغاء الحصة' }) }).last();
  const [day, time] = [await lesson.locator('p').nth(0).innerText(), await lesson.locator('p').nth(1).innerText()];
  const same = page.getByRole('article').filter({ hasText: day }).filter({ hasText: time });
  await lesson.getByRole('button', { name: 'إلغاء الحصة' }).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog.getByText('حسب سياسة الأكاديمية:')).toBeVisible();
  await dialog.getByLabel('السبب').selectOption({ index: 1 });
  await dialog.getByLabel(/ملاحظة للأكاديمية/).fill('لدينا سفر هذا الأسبوع.');
  await dialog.getByRole('button', { name: 'نعم، ألغِها' }).click();
  await expect(dialog).toBeHidden();
  // It no longer waits ahead: gone from the lessons to come, or there as cancelled.
  await expect(same.getByRole('button', { name: 'إلغاء الحصة' })).toHaveCount(0);
  await page.reload();
  await expect(same.getByRole('button', { name: 'إلغاء الحصة' })).toHaveCount(0);
});

test('a guardian rates a lesson that took place', async ({ page }) => {
  await page.goto('/ar/family/lessons?when=past');
  await page.getByRole('button', { name: 'قيّم الحصة' }).first().click();
  const dialog = page.getByRole('dialog');
  await dialog.getByRole('radio', { name: '4 من 5' }).click();
  await expect(dialog.getByText('ما الذي أعجبكم؟')).toBeVisible();
  await dialog.getByRole('button', { name: 'أرسل التقييم' }).click();
  await expect(dialog).toBeHidden();
  await expect(page.getByText('شكرًا! وصل تقييمك.')).toBeVisible();
});

test('what is owed is paid on the academy\'s payment page', async ({ page }) => {
  await page.goto('/ar/family/money');
  await expect(page.getByText('الفواتير', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: /^ادفع / }).first().click();
  // The academy's own gateway page (the demo gateway here); the invoice is paid only when it says so.
  await page.waitForURL(/\/pay\//);
});

test('a guardian who paid by transfer sends the receipt; the academy approves it, and the invoice is paid', async ({ page }) => {
  await page.goto('/ar/family/money');
  const owed = page.locator('section[aria-labelledby="owed"] article').filter({ has: page.getByRole('button', { name: 'دفعت بالتحويل' }) }).first();
  const number = /[A-Z]+-\d+/.exec(await owed.locator('p').first().innerText())?.[0];
  expect(number).toBeTruthy();
  await owed.getByRole('button', { name: 'دفعت بالتحويل' }).click();
  const form = page.locator('form').filter({ hasText: 'أرسل إثبات الدفع' });
  await expect(form.getByText('مصرف الراجحي', { exact: false })).toBeVisible();

  // No receipt yet: nothing is sent.
  await form.getByRole('button', { name: 'أرسل الإثبات' }).click();
  await expect(form.getByText('اختر الإيصال أولًا.')).toBeVisible();

  const reference = `TRF-E2E-${Date.now()}`;
  await form.getByText('تحويل بنكي', { exact: true }).click();
  await form.getByLabel('رقم التحويل').fill(reference);
  // A tiny picture of our own as the receipt (1×1 PNG).
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64');
  await form.locator('input[type="file"]').setInputFiles({ name: 'receipt.png', mimeType: 'image/png', buffer: png });
  await form.getByRole('button', { name: 'أرسل الإثبات' }).click();

  await expect(page.getByText('أُرسل الإثبات. سنخبرك فور تأكيد الأكاديمية له.')).toBeVisible();
  await expect(page.locator('section[aria-labelledby="owed"] article').filter({ hasText: number! }).getByText(/وهو بانتظار الأكاديمية/)).toBeVisible();

  // The accountant sees the money arrived and approves it (ApprovePaymentProof, as the back office does).
  expect(approveProof(reference)).toBe(number);
  await page.reload();
  await expect(page.locator('section[aria-labelledby="owed"] article').filter({ hasText: number! })).toHaveCount(0);
  const line = page.locator('section[aria-labelledby="invoices"] li').filter({ hasText: number! });
  await expect(line.getByText('مدفوعة', { exact: true })).toBeVisible();
  await expect(line.getByText(/قُبل إثبات دفع/)).toBeVisible();
});

test('an invoice opens as its tax document, for its payer', async ({ page }) => {
  await page.goto('/ar/family/money');
  const line = page.locator('section[aria-labelledby="invoices"] li').first();
  const number = await line.locator('span.font-semibold').first().innerText();
  const href = (await line.getByRole('link', { name: 'افتح الفاتورة' }).getAttribute('href'))!;
  // A signed address on the academy's host (its own DNS may not be here: the API, as that host).
  const url = new URL(href);
  const document = await page.request.get(`${API}${url.pathname}${url.search}`, { headers: { Host: url.host } });
  expect(document.status()).toBe(200);
  expect(await document.text()).toContain(number);
  // One character of the signature changed, whatever it was: an address nobody signed.
  const forged = url.search.replace(/signature=(\w)/, (_, first: string) => `signature=${first === '0' ? '1' : '0'}`);
  expect(forged).not.toBe(url.search);
  const tampered = await page.request.get(`${API}${url.pathname}${forged}`, { headers: { Host: url.host } });
  expect(tampered.status()).toBe(403);
});

test('a guardian reads the teacher\'s reports on the course, without what is for the academy only', async ({ page }) => {
  await page.goto('/ar/family/journey');
  await page.getByRole('link', { name: 'المنهج وتقارير المعلّم' }).first().click();
  await expect(page).toHaveURL(/\/ar\/family\/progress\/[0-9a-f-]{36}$/);
  await expect(page.getByRole('heading', { name: 'التقارير' })).toBeVisible();
  await expect(page.getByText('بداية ممتازة: يميّز زياد بين الاسم والفعل بسهولة.')).toBeVisible();
  // The teacher's note to the academy and the question it keeps to itself never reach a family.
  await expect(page.getByText('قال ولي الأمر إن لديه امتحانات في المدرسة هذا الأسبوع.')).toHaveCount(0);
  await expect(page.getByText('ملاحظة للمشرف')).toHaveCount(0);
  await fitsTheScreen(page);
  await noRawKeys(page);
});

test('a guardian cheers their child on', async ({ page }) => {
  await page.goto('/ar/family/journey');
  const cheers = page.getByRole('region', { name: /^شجّع / });
  await expect(cheers).toBeVisible();
  await expect(cheers.getByText('استنفدت تشجيعات اليوم')).toHaveCount(0);
  await cheers.getByRole('button', { name: 'نجمة' }).click();
  await expect(page.getByText(/^وصل التشجيع إلى /)).toBeVisible();
  // Only the student opens the week's chest.
  await expect(page.getByRole('button', { name: 'افتح الصندوق' })).toHaveCount(0);
});

test('a guardian writes to the academy about their child, and reads it back in the ticket', async ({ page }) => {
  await page.goto('/ar/family/help');
  await expect(page.locator('h1')).toHaveText('المساعدة');
  await page.getByRole('button', { name: 'تذكرة جديدة' }).click();
  const subject = `سؤال عن مواعيد الحصص ${Date.now()}`;
  await page.getByLabel('العنوان').fill(subject);
  await page.getByLabel('أخبرنا بما حدث').fill('هل يمكن تغيير موعد حصة الأربعاء إلى ما بعد المغرب؟');
  await page.getByRole('button', { name: 'أرسل' }).click();
  await expect(page).toHaveURL(/\/ar\/family\/help\/[0-9a-f-]{36}$/);
  await expect(page.getByText(subject)).toBeVisible();
  await expect(page.getByText('هل يمكن تغيير موعد حصة الأربعاء إلى ما بعد المغرب؟')).toBeVisible();
  await fitsTheScreen(page);
  // A ticket that is not there is not found.
  expect((await page.goto('/ar/family/help/not-a-ticket'))?.status()).toBe(404);
});

test('a guardian tells the safeguarding leads that something is wrong, and gets a number back', async ({ page }) => {
  await page.goto('/ar/family/tell-us');
  await expect(page.locator('h1')).toHaveText('أبلغ عن قلق على سلامة طفل');
  const form = page.locator('form');
  await form.getByLabel('ماذا حدث؟').fill('أرسل أحدهم إلى ابني رسالة غريبة بعد الحصة.');
  // What it is about is asked for first.
  await form.getByRole('button', { name: 'أرسل إلى الأكاديمية' }).click();
  await expect(form.getByText('اختر موضوع البلاغ.')).toBeVisible();
  await form.locator('label:has(input[name="category"])').first().click();
  await form.getByRole('button', { name: 'أرسل إلى الأكاديمية' }).click();
  await expect(page.getByText(/^رقم #?\d+$/).first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'أخبرنا بشيء آخر' })).toBeVisible();
});

test('a guardian agrees to their child\'s lessons being recorded, and takes it back', async ({ page }) => {
  await page.goto('/ar/family/recordings');
  const agree = page.getByRole('button', { name: 'أوافق', exact: true });
  const withdraw = page.getByRole('button', { name: 'اسحب الموافقة' });
  await expect(agree.or(withdraw)).toBeVisible();
  if (await withdraw.isVisible()) {
    page.once('dialog', (d) => d.accept());
    await withdraw.click();
  }

  await page.getByRole('checkbox').check();
  await agree.click();
  await expect(withdraw).toBeVisible();
  await expect(page.getByText('غير موافق')).toHaveCount(0);

  page.once('dialog', (d) => d.accept());
  await withdraw.click();
  await expect(agree).toBeVisible();
});

test('a guardian puts their child\'s details right, and asks the academy for what cannot be changed here', async ({ page }) => {
  await page.goto('/ar/family/my-data');
  await fitsTheScreen(page);
  // The child's card: the facts, corrected on the spot.
  const child = page.getByRole('article').filter({ has: page.getByText('من أبنائك') }).first();
  const fix = child.locator('[data-part="fix"]');
  // A grade other than the one on file, so it changes on every run.
  const grade = (await fix.locator('dt', { hasText: 'الصف' }).locator('xpath=following-sibling::dd[1]').innerText()) === 'الجامعة' ? 'الثالث الثانوي' : 'الجامعة';
  await fix.getByRole('button', { name: 'صحّح' }).click();
  await fix.getByLabel('الصف').selectOption({ label: grade });
  await fix.getByRole('button', { name: 'احفظ' }).click();
  await expect(fix.getByText('تم الحفظ.')).toBeVisible();
  await expect(fix.getByText(grade, { exact: true })).toBeVisible();

  // The rest is a request the academy answers; it can be taken back while it waits.
  await fix.getByRole('button', { name: 'اطلب تصحيحًا' }).click();
  await fix.getByLabel('ما الذي يحتاج إلى تصحيح').fill('تاريخ الميلاد المسجّل خطأ: الصحيح 2013.');
  await fix.getByRole('button', { name: 'أرسل الطلب' }).click();
  await expect(fix.getByText(/قُدّم الطلب: سترد الأكاديمية قبل/)).toBeVisible();
  await fix.getByRole('button', { name: 'ألغِ الطلب' }).click();
  await expect(fix.getByRole('button', { name: 'اطلب تصحيحًا' })).toBeVisible();
});

test('a guardian\'s account shows the email and number, and a new one waits for its code', async ({ page }) => {
  await page.goto('/ar/family/account');
  await fitsTheScreen(page);
  const email = page.locator('li[data-kind="email"]');
  await expect(email).toContainText('@');
  await email.getByRole('button', { name: 'غيّر البريد الإلكتروني' }).click();
  await page.getByLabel('البريد الإلكتروني الجديد').fill('not an email');
  await page.getByRole('button', { name: 'أرسل رمزًا' }).click();
  await expect(page.getByText('اكتب بريدًا إلكترونيًا صحيحًا.')).toBeVisible();
  await page.getByRole('button', { name: 'رجوع' }).click();
});

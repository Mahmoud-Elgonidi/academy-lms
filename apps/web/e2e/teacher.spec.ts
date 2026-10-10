import { expect, fitsTheScreen, test } from './helpers';

test('every teacher page opens, fits a phone, and reads right to left', async ({ page }) => {
  for (const path of ['/ar/teach', '/ar/teach/week', '/ar/teach/reports', '/ar/teach/marking', '/ar/teach/students', '/ar/teach/messages', '/ar/teach/trials', '/ar/teach/hours', '/ar/teach/time-off', '/ar/teach/earnings', '/ar/teach/performance', '/ar/teach/recordings', '/ar/teach/notifications', '/ar/teach/more']) {
    const response = await page.goto(path);
    expect(response?.status(), path).toBe(200);
    await expect(page.locator('h1').first(), path).toBeVisible();
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await fitsTheScreen(page);
  }
  // The tab bar gets around on a phone.
  await page.goto('/ar/teach');
  await page.getByRole('navigation').getByRole('link', { name: 'أسبوعي' }).click();
  await expect(page).toHaveURL(/\/ar\/teach\/week$/);
});

test('the week moves back and forth', async ({ page }) => {
  await page.goto('/ar/teach/week');
  const title = await page.locator('h1').innerText();
  await page.getByRole('link', { name: 'الأسبوع الجاي' }).click();
  await expect(page).toHaveURL(/from=\d{4}-\d{2}-\d{2}/);
  await expect(page.locator('h1')).not.toHaveText(title);
  await page.getByRole('link', { name: 'الأسبوع ده' }).click();
  await expect(page.locator('h1')).toHaveText(title);
});

test('a report without the ratings is not sent', async ({ page }) => {
  await page.goto('/ar/teach/reports');
  const open = page.getByRole('region', { name: 'مستنية منك' }).getByRole('link').filter({ hasNotText: 'ملاحظة غياب' });
  test.skip((await open.count()) === 0, 'no lesson report waiting in this data');
  await open.first().click();
  await page.getByRole('button', { name: 'ابعت التقرير' }).click();
  await expect(page.getByText('اختار التركيز والمستوى العام.')).toBeVisible();
  await expect(page).toHaveURL(/\/ar\/teach\/reports\/[\w-]+$/);
});

test('a lesson report is written and sent', async ({ page }) => {
  await page.goto('/ar/teach/reports');
  const open = page.getByRole('region', { name: 'مستنية منك' }).getByRole('link').filter({ hasNotText: 'ملاحظة غياب' });
  test.skip((await open.count()) === 0, 'no lesson report waiting in this data');
  await open.first().click();
  await expect(page.getByText('خطة النهارده')).toBeVisible();

  // What happened with each lesson of the plan, then focus and overall.
  const plans = page.getByRole('radiogroup').filter({ has: page.getByRole('radio', { name: 'اتقن' }) });
  for (let i = 0; i < (await plans.count()); i++) await plans.nth(i).getByRole('radio', { name: 'اتدرّب عليه' }).click();
  await page.getByRole('radiogroup', { name: 'التركيز والتفاعل' }).getByRole('radio', { name: '4 من 5' }).click();
  await page.getByRole('radiogroup', { name: 'المستوى العام' }).getByRole('radio', { name: '5 من 5' }).click();
  await page.getByLabel('ملاحظات للطالب وولي الأمر').fill('شغل حلو النهارده، كمّل على كده.');
  // The subject's own questions (Arabic grammar in the demo): stars for parsing, a number of new words.
  if ((await page.getByRole('heading', { name: 'أسئلة الأكاديمية' }).count()) > 0) {
    await page.getByRole('button', { name: 'ابعت التقرير' }).click();
    await expect(page.getByText('جاوب على "الإعراب".')).toBeVisible();
    await page.getByRole('radiogroup', { name: 'الإعراب' }).getByRole('radio', { name: '4 من 5' }).click();
    await page.getByLabel('الكلمات الجديدة').fill('6');
  }
  await page.getByLabel('ادّي واجب').check();
  await page.getByLabel('الواجب', { exact: true }).fill('حل تمرين ص 20');
  await page.getByRole('button', { name: 'ابعت التقرير' }).click();

  await expect(page).toHaveURL(/\/ar\/teach\/reports\?sent=1$/);
  await expect(page.getByText('التقرير اتبعت.')).toBeVisible();
});

test('handed-in homework is marked, and its recording plays through the portal', async ({ page }) => {
  await page.goto('/ar/teach/marking');
  const cards = page.locator('article');
  test.skip((await cards.count()) === 0, 'no homework waiting in this data');
  const card = cards.first();
  const title = await card.locator('p').nth(1).innerText();

  const audio = card.locator('audio');
  if ((await audio.count()) > 0) {
    const src = await audio.first().getAttribute('src');
    const file = await page.request.get(src!);
    expect(file.status()).toBe(200);
    expect(file.headers()['content-type']).toMatch(/^audio\//);
  }

  await card.getByRole('radio', { name: '4' }).click();
  await card.getByLabel('كلمتك للطالب').fill('أحسنت، القراءة واضحة.');
  await card.getByRole('button', { name: 'سجّل التصحيح' }).click();
  await expect(page.locator('article').filter({ hasText: title })).toHaveCount(0);

  await page.getByRole('link', { name: 'اتصحّح' }).click();
  await expect(page.locator('article').filter({ hasText: title }).first()).toBeVisible();
});

test('a file handed in by someone else is not served', async ({ page }) => {
  const response = await page.request.get('/bff/submissions/00000000-0000-0000-0000-000000000000/file');
  expect([403, 404]).toContain(response.status());
});

test('time off is asked for and withdrawn', async ({ page }) => {
  await page.goto('/ar/teach/time-off');
  // A day far enough ahead, different on each run (a request left by an earlier run would overlap).
  const day = new Date(Date.now() + (40 + Math.floor(Math.random() * 300)) * 86_400_000).toISOString().slice(0, 10);
  const note = `مشوار ضروري ${Date.now()}`;
  await page.getByRole('radio', { name: 'جزء من يوم' }).click();
  await page.getByLabel('اليوم').fill(day);
  await page.getByLabel('من الساعة').fill('10:00');
  await page.getByLabel('لحد الساعة').fill('12:00');
  await page.getByLabel('السبب').selectOption('personal');
  await page.getByLabel(/ملاحظة/).fill(note);
  await page.getByRole('button', { name: 'ابعت الطلب' }).click();
  await expect(page.getByText('الطلب اتبعت. هيوصلك الرد.')).toBeVisible();

  const request = page.locator('article').filter({ hasText: note });
  await expect(request).toContainText('ظرف شخصي');
  await request.getByRole('button', { name: 'اسحبه' }).click();
  await expect(request.getByRole('button', { name: 'اسحبه' })).toHaveCount(0);
  await expect(request).toContainText('اتسحب');
});

test('new hours are proposed as a draft, talked about, and withdrawn', async ({ page }) => {
  await page.goto('/ar/teach/hours');
  // Leave whatever proposal an earlier run left open.
  const withdraw = page.getByRole('button', { name: 'اسحب المقترح' });
  if (await withdraw.isVisible()) {
    page.once('dialog', (d) => d.accept());
    await withdraw.click();
    await expect(page.getByText('المقترح اتسحب.')).toBeVisible();
  }

  await page.getByRole('button', { name: 'اقترح مواعيد جديدة' }).click();
  const saturday = page.locator('li').filter({ has: page.getByText('السبت', { exact: true }) }).filter({ has: page.getByRole('button', { name: 'ضيف وقت' }) });
  await saturday.getByRole('button', { name: 'ضيف وقت' }).click();
  await expect(saturday.locator('input[type=time]')).toHaveCount(2);
  await page.getByLabel(/ملاحظة للأكاديمية/).fill('عايز أضيف السبت العصر.');
  await page.getByRole('button', { name: 'احفظ مسودة' }).click();
  await expect(page.getByText('المسودة اتحفظت.')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'مقترحك' })).toBeVisible();
  await expect(page.locator('li').filter({ hasText: 'السبت' }).filter({ hasText: '17:00–19:00' }).first()).toBeVisible();
  await page.getByLabel('اكتب رسالة…').fill('ممكن نبدأ من الأسبوع الجاي؟');
  await page.getByRole('button', { name: 'ابعت' }).click();
  await expect(page.getByText('ممكن نبدأ من الأسبوع الجاي؟')).toBeVisible();

  page.once('dialog', (d) => d.accept());
  await page.getByRole('button', { name: 'اسحب المقترح' }).click();
  await expect(page.getByText('المقترح اتسحب.')).toBeVisible();
});

test('the language switches, keeping the page', async ({ page }) => {
  await page.goto('/ar/teach/more');
  await page.getByRole('link', { name: 'English' }).click();
  await expect(page).toHaveURL(/\/en\/teach\/more$/);
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
  await expect(page.getByRole('heading', { name: 'Menu' })).toBeVisible();
});

test('the teacher talks with a student and their family, or reads why not', async ({ page }) => {
  await page.goto('/ar/teach/messages');
  await expect(page.locator('h1')).toHaveText('الرسايل');
  await expect(page.getByText('ولي أمر الطالب موجود في كل محادثة')).toBeVisible();
  await page.locator('main li').first().locator('a, button').first().click();
  await expect(page).toHaveURL(/\/ar\/teach\/messages\/[0-9a-f-]+$/);
  // Either the teacher may write here, or the page says why not (a minor and a check still waiting, the academy's pause).
  await expect(page.locator('textarea[name="body"]').or(page.getByText(/وقّفت|مابقاش بيدرّس|مقفولة/))).toBeVisible();
});

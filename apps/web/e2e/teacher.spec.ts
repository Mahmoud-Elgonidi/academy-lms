import { fixtures, lessonNow } from './backend';
import { expect, fitsTheScreen, noRawKeys, test } from './helpers';
import { TEACHER_PAGES } from './pages';

test('every teacher page opens, fits a phone, and reads right to left', async ({ page }) => {
  test.setTimeout(120_000);
  for (const path of TEACHER_PAGES.map((p) => `/ar/teach${p}`)) {
    const response = await page.goto(path);
    expect(response?.status(), path).toBe(200);
    await expect(page.locator('h1').first(), path).toBeVisible();
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await fitsTheScreen(page);
    await noRawKeys(page);
  }
  // The tab bar gets around on a phone.
  await page.goto('/ar/teach');
  await page.getByRole('navigation').getByRole('link', { name: 'أسبوعي' }).click();
  await expect(page).toHaveURL(/\/ar\/teach\/week$/);
});

test('the week moves back and forth', async ({ page }) => {
  await page.goto('/ar/teach/week');
  const title = await page.locator('h1').innerText();
  await page.getByRole('link', { name: 'الأسبوع القادم' }).click();
  await expect(page).toHaveURL(/from=\d{4}-\d{2}-\d{2}/);
  await expect(page.locator('h1')).not.toHaveText(title);
  await page.getByRole('link', { name: 'هذا الأسبوع' }).click();
  await expect(page.locator('h1')).toHaveText(title);
});

test('a report without the ratings is not sent', async ({ page }) => {
  await page.goto('/ar/teach/reports');
  const open = page.getByRole('region', { name: 'بانتظارك' }).getByRole('link').filter({ hasNotText: 'ملاحظة غياب' });
  await open.first().click();
  await page.getByRole('button', { name: 'أرسل التقرير' }).click();
  await expect(page.getByText('اختر التركيز والمستوى العام.')).toBeVisible();
  await expect(page).toHaveURL(/\/ar\/teach\/reports\/[\w-]+$/);
});

test('a lesson report is written and sent', async ({ page }) => {
  await page.goto('/ar/teach/reports');
  const open = page.getByRole('region', { name: 'بانتظارك' }).getByRole('link').filter({ hasNotText: 'ملاحظة غياب' });
  await open.first().click();
  await expect(page.getByText('خطة اليوم')).toBeVisible();

  // What happened with each lesson of the plan, then focus and overall.
  const plans = page.getByRole('radiogroup').filter({ has: page.getByRole('radio', { name: 'أتقنه' }) });
  for (let i = 0; i < (await plans.count()); i++) await plans.nth(i).getByRole('radio', { name: 'تدرّب عليه' }).click();
  await page.getByRole('radiogroup', { name: 'التركيز والتفاعل' }).getByRole('radio', { name: '4 من 5' }).click();
  await page.getByRole('radiogroup', { name: 'المستوى العام' }).getByRole('radio', { name: '5 من 5' }).click();
  await page.getByLabel('ملاحظات للطالب وولي الأمر').fill('عمل جميل اليوم، استمر على هذا.');
  // The subject's own questions (Arabic grammar in the demo): stars for parsing, a number of new words.
  if ((await page.getByRole('heading', { name: 'أسئلة الأكاديمية' }).count()) > 0) {
    await page.getByRole('button', { name: 'أرسل التقرير' }).click();
    await expect(page.getByText('أجب عن "الإعراب".')).toBeVisible();
    await page.getByRole('radiogroup', { name: 'الإعراب' }).getByRole('radio', { name: '4 من 5' }).click();
    await page.getByLabel('الكلمات الجديدة').fill('6');
  }
  await page.getByLabel('أعطِ واجبًا').check();
  await page.getByLabel('الواجب', { exact: true }).fill('حل تمرين ص 20');
  await page.getByRole('button', { name: 'أرسل التقرير' }).click();

  await expect(page).toHaveURL(/\/ar\/teach\/reports\?sent=1$/);
  await expect(page.getByText('أُرسل التقرير.')).toBeVisible();
});

test('handed-in homework is marked, and its recording plays through the portal', async ({ page }) => {
  // Ziad's recording, handed in for this run (E2eResetSeeder).
  const title = fixtures().homework.to_mark;
  await page.goto('/ar/teach/marking');
  const card = page.locator('article').filter({ hasText: title });
  await expect(card).toHaveCount(1);

  const audio = card.locator('audio');
  await expect(audio).toHaveCount(1);
  const file = await page.request.get((await audio.getAttribute('src'))!);
  expect(file.status()).toBe(200);
  expect(file.headers()['content-type']).toMatch(/^audio\//);

  await card.getByRole('radio', { name: '4' }).click();
  await card.getByLabel('كلمتك للطالب').fill('أحسنت، القراءة واضحة.');
  await card.getByRole('button', { name: 'سجّل التصحيح' }).click();
  await expect(page.locator('article').filter({ hasText: title })).toHaveCount(0);

  await page.getByRole('link', { name: 'صُحّح' }).click();
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
  await page.getByLabel('حتى الساعة').fill('12:00');
  await page.getByLabel('السبب').selectOption('personal');
  await page.getByLabel(/ملاحظة/).fill(note);
  await page.getByRole('button', { name: 'أرسل الطلب' }).click();
  await expect(page.getByText('أُرسل الطلب. سيصلك الرد.')).toBeVisible();

  const request = page.locator('article').filter({ hasText: note });
  await expect(request).toContainText('ظرف شخصي');
  await request.getByRole('button', { name: 'اسحبه' }).click();
  await expect(request.getByRole('button', { name: 'اسحبه' })).toHaveCount(0);
  await expect(request).toContainText('مسحوبة');
});

test('new hours are drafted, sent, talked about beside the page, and withdrawn', async ({ page }) => {
  await page.goto('/ar/teach/hours');
  // Leave whatever proposal an earlier run left open.
  const withdraw = page.getByRole('button', { name: 'اسحب المقترح' });
  if (await withdraw.first().isVisible()) {
    page.once('dialog', (d) => d.accept());
    await withdraw.first().click();
    await expect(page.getByText('سُحب المقترح.')).toBeVisible();
  }

  // A draft left from an earlier run opens with "edit and send"; else a new proposal starts.
  const steps = page.getByRole('list', { name: 'مراحل المقترح' });
  const resume = page.getByRole('button', { name: 'عدّل وأرسل' });
  if (await resume.isVisible()) await resume.click();
  else await page.getByRole('button', { name: 'اقترح مواعيد جديدة' }).click();
  const saturday = page.locator('li').filter({ has: page.getByText('السبت', { exact: true }) }).filter({ has: page.getByRole('button', { name: 'أضف وقتًا' }) });
  await saturday.getByRole('button', { name: 'أضف وقتًا' }).click();
  await page.getByLabel(/ملاحظة للأكاديمية/).fill('أريد إضافة عصر السبت.');
  await page.getByRole('button', { name: 'احفظ مسودة' }).click();
  await expect(page.getByText('حُفظت المسودة.')).toBeVisible();
  // A draft: its step, and one thing to do.
  await expect(steps.locator('[aria-current="step"]')).toContainText('مسودة');
  await expect(page.getByRole('button', { name: 'المحادثة (0)' })).toHaveCount(0);
  // The academy's clock in the demo: 12 hours.
  await expect(page.locator('li').filter({ hasText: 'السبت' }).filter({ hasText: /\d:\d\d [صم]–/ }).first()).toBeVisible();

  await page.getByRole('button', { name: 'عدّل وأرسل' }).click();
  await page.getByRole('button', { name: 'أرسل إلى الأكاديمية' }).click();
  await expect(page.getByText('أُرسل المقترح إلى الأكاديمية.')).toBeVisible();
  await expect(steps.locator('[aria-current="step"]')).toContainText('قيد المراجعة');

  // The conversation opens beside the page.
  await page.getByRole('button', { name: /المحادثة \(\d+\)/ }).click();
  const talk = page.getByRole('dialog', { name: 'المحادثة مع الأكاديمية' });
  await expect(talk).toBeVisible();
  await expect(talk.getByText(/أُرسل المقترح ·/)).toBeVisible();
  await talk.getByLabel('اكتب رسالة…').fill('هل يمكن أن نبدأ من الأسبوع القادم؟');
  await talk.getByRole('button', { name: 'أرسل' }).click();
  await expect(talk.getByText('هل يمكن أن نبدأ من الأسبوع القادم؟')).toBeVisible();
  await talk.getByRole('button', { name: 'إغلاق' }).click();

  // In review, the one thing to do is withdraw.
  page.once('dialog', (d) => d.accept());
  await page.getByRole('button', { name: 'اسحب المقترح' }).click();
  await expect(page.getByText('سُحب المقترح.')).toBeVisible();
});

test('the teacher reads clocks in 24 hours, then goes back to the academy\'s 12', async ({ page }) => {
  // My account › "شكل الساعة": a choice already in place is left as it is.
  const choose = async (name: string) => {
    await page.goto('/ar/teach/account');
    const form = page.getByRole('form', { name: 'شكل الساعة' });
    const radio = form.getByRole('radio', { name, exact: true });
    if (await radio.isChecked()) return;
    await radio.check();
    await form.getByRole('button', { name: 'احفظ' }).click();
    await expect(form.getByText('تم الحفظ. ستُكتب المواعيد بهذا الشكل من الآن.')).toBeVisible();
  };
  // Khaled's approved week in the demo: Sunday to Thursday, 15:00 to 21:00 on his clock.
  const sunday = page.locator('li').filter({ has: page.getByText('الأحد', { exact: true }) });

  await choose('24 ساعة (17:00)');
  try {
    await page.goto('/ar/teach/hours');
    await expect(sunday.filter({ hasText: '15:00–21:00' }).first()).toBeVisible();
    await expect(page.locator('main bdi').filter({ hasText: /\d [صم]/ })).toHaveCount(0);
  } finally {
    // The demo as it was found: following the academy.
    await choose('مثل الأكاديمية (12 ساعة)');
  }
  await page.goto('/ar/teach/hours');
  await expect(sunday.filter({ hasText: '3:00 م–9:00 م' }).first()).toBeVisible();
});

test('the language switches, keeping the page', async ({ page }) => {
  await page.goto('/ar/teach/more');
  await page.getByRole('link', { name: 'English' }).click();
  await expect(page).toHaveURL(/\/en\/teach\/more$/);
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
  await expect(page.getByRole('heading', { name: 'More' })).toBeVisible();
});

test('the teacher talks with a student and their family, or reads why not', async ({ page }) => {
  await page.goto('/ar/teach/messages');
  await expect(page.locator('h1')).toHaveText('الرسائل');
  await expect(page.getByText('ولي أمر الطالب موجود في كل محادثة')).toBeVisible();
  await page.locator('main li').first().locator('a, button').first().click();
  await expect(page).toHaveURL(/\/ar\/teach\/messages\/[0-9a-f-]+$/);
  // Either the teacher may write here, or the page says why not (a minor and a check still waiting, the academy's pause).
  await expect(page.locator('textarea[name="body"]').or(page.getByText(/أوقفت|لم يعد هذا المعلّم يدرّس|مغلقة/))).toBeVisible();
});

test('the class page opens a lesson on the teacher\'s outside link in a new tab', async ({ page, context }) => {
  // The outside meeting itself is not ours to load here.
  await context.route('https://meet.google.com/**', (route) => route.fulfill({ contentType: 'text/html', body: '<title>Meet</title>' }));
  await page.goto(`/ar/teach/lessons/${lessonNow('teacher')}`);
  await expect(page.locator('h1')).toHaveText(/^مع /);
  await fitsTheScreen(page);
  const tab = page.waitForEvent('popup');
  await page.getByRole('button', { name: 'ادخل الغرفة' }).click();
  await (await tab).waitForURL(/^https:\/\/meet\.google\.com\//);
  await expect(page.getByText('هذه الحصة على رابط خارج المنصة، وسيفتح في علامة تبويب جديدة.')).toBeVisible();
  await expect(page.getByRole('link', { name: 'افتح رابط الحصة' })).toHaveAttribute('href', /^https:\/\/meet\.google\.com\//);
});

test('a student\'s course opens on its roadmap, without the family\'s contact details', async ({ page }) => {
  await page.goto('/ar/teach/students');
  await page.locator('a[href*="/teach/students/"]').filter({ hasText: 'زياد' }).first().click();
  await expect(page).toHaveURL(/\/ar\/teach\/students\/[0-9a-f-]{36}$/);
  await expect(page.locator('h1')).toHaveText(/زياد/);
  // A teacher never sees a family's email or number.
  await expect(page.getByText(/@demo\.test|\+9655/)).toHaveCount(0);
  await fitsTheScreen(page);
  await noRawKeys(page);
});

test('a lesson that is not the teacher\'s, or no lesson at all, is not found', async ({ page }) => {
  for (const path of ['/ar/teach/reports/not-a-report', '/ar/teach/students/not-a-course', '/ar/teach/lessons/not-a-lesson', '/ar/teach/help/not-a-ticket', '/ar/teach/messages/not-a-conversation']) {
    const response = await page.goto(path);
    expect(response?.status(), path).toBe(404);
  }
});

test('a teacher writes to the academy for help, and reads it back in the ticket', async ({ page }) => {
  await page.goto('/ar/teach/help');
  await expect(page.locator('h1')).toHaveText('المساعدة');
  await page.getByRole('button', { name: 'تذكرة جديدة' }).click();
  const subject = `الصوت ينقطع في الحصة ${Date.now()}`;
  await page.getByLabel('العنوان').fill(subject);
  await page.getByLabel('أخبرنا بما حدث').fill('الصوت ينقطع كثيرًا منذ بداية الحصة.');
  await page.getByRole('button', { name: 'أرسل' }).click();
  await expect(page).toHaveURL(/\/ar\/teach\/help\/[0-9a-f-]{36}$/);
  await expect(page.getByText(subject)).toBeVisible();
  await expect(page.getByText('الصوت ينقطع كثيرًا منذ بداية الحصة.')).toBeVisible();
  await fitsTheScreen(page);
  await page.getByRole('link', { name: 'تذاكري' }).first().click();
  await expect(page.getByText(subject)).toBeVisible();
});

test('a teacher tells the safeguarding leads about a student, and gets a number back', async ({ page }) => {
  await page.goto('/ar/teach/concern');
  await expect(page.locator('h1')).toHaveText('أبلغ عن قلق على سلامة طالب');
  const form = page.locator('form');
  await form.getByLabel('ماذا حدث؟').fill('قال الطالب في الحصة إن أحدًا يضايقه على التطبيق.');
  // What it is about is asked for first.
  await form.getByRole('button', { name: 'أرسل إلى مسؤولي الحماية' }).click();
  await expect(form.getByText('اختر موضوع البلاغ.')).toBeVisible();
  await form.locator('label:has(input[name="category"])').first().click();
  await form.getByLabel('أي طالب؟').selectOption({ index: 1 });
  await form.getByRole('button', { name: 'أرسل إلى مسؤولي الحماية' }).click();
  await expect(page.getByText(/^رقم #?\d+$/).first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'أبلغ عن أمر آخر' })).toBeVisible();
});

test('every teacher page reads in English, left to right', async ({ page }) => {
  test.setTimeout(120_000);
  for (const path of TEACHER_PAGES.map((p) => `/en/teach${p}`)) {
    const response = await page.goto(path);
    expect(response?.status(), path).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    await expect(page.locator('h1').first(), path).toBeVisible();
    await fitsTheScreen(page);
    await noRawKeys(page);
  }
  await page.goto('/en/teach');
  await page.getByRole('navigation').getByRole('link', { name: 'My week' }).click();
  await expect(page).toHaveURL(/\/en\/teach\/week$/);
  await expect(page.getByRole('link', { name: 'Next week' })).toBeVisible();
});

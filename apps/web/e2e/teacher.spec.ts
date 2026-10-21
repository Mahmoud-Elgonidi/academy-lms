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
  await page.getByRole('link', { name: 'الأسبوع الجاي' }).click();
  await expect(page).toHaveURL(/from=\d{4}-\d{2}-\d{2}/);
  await expect(page.locator('h1')).not.toHaveText(title);
  await page.getByRole('link', { name: 'الأسبوع ده' }).click();
  await expect(page.locator('h1')).toHaveText(title);
});

test('a report without the ratings is not sent', async ({ page }) => {
  await page.goto('/ar/teach/reports');
  const open = page.getByRole('region', { name: 'مستنية منك' }).getByRole('link').filter({ hasNotText: 'ملاحظة غياب' });
  await open.first().click();
  await page.getByRole('button', { name: 'ابعت التقرير' }).click();
  await expect(page.getByText('اختار التركيز والمستوى العام.')).toBeVisible();
  await expect(page).toHaveURL(/\/ar\/teach\/reports\/[\w-]+$/);
});

test('a lesson report is written and sent', async ({ page }) => {
  await page.goto('/ar/teach/reports');
  const open = page.getByRole('region', { name: 'مستنية منك' }).getByRole('link').filter({ hasNotText: 'ملاحظة غياب' });
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
  await expect(page.getByRole('heading', { name: 'More' })).toBeVisible();
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

test('the class page opens a lesson on the teacher\'s outside link in a new tab', async ({ page, context }) => {
  // The outside meeting itself is not ours to load here.
  await context.route('https://meet.google.com/**', (route) => route.fulfill({ contentType: 'text/html', body: '<title>Meet</title>' }));
  await page.goto(`/ar/teach/lessons/${lessonNow('teacher')}`);
  await expect(page.locator('h1')).toHaveText(/^مع /);
  await fitsTheScreen(page);
  const tab = page.waitForEvent('popup');
  await page.getByRole('button', { name: 'ادخل الأوضة' }).click();
  await (await tab).waitForURL(/^https:\/\/meet\.google\.com\//);
  await expect(page.getByText('الحصة دي على لينك برّه المنصة. هيفتح في تاب جديد.')).toBeVisible();
  await expect(page.getByRole('link', { name: 'افتح لينك الحصة' })).toHaveAttribute('href', /^https:\/\/meet\.google\.com\//);
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
  const subject = `الصوت بيقطع في الحصة ${Date.now()}`;
  await page.getByLabel('العنوان').fill(subject);
  await page.getByLabel('احكيلنا حصل إيه').fill('الصوت بيقطع كل شوية من ساعة ما بدأت الحصة.');
  await page.getByRole('button', { name: 'ابعت' }).click();
  await expect(page).toHaveURL(/\/ar\/teach\/help\/[0-9a-f-]{36}$/);
  await expect(page.getByText(subject)).toBeVisible();
  await expect(page.getByText('الصوت بيقطع كل شوية من ساعة ما بدأت الحصة.')).toBeVisible();
  await fitsTheScreen(page);
  await page.getByRole('link', { name: 'تذاكري' }).first().click();
  await expect(page.getByText(subject)).toBeVisible();
});

test('a teacher tells the safeguarding leads about a student, and gets a number back', async ({ page }) => {
  await page.goto('/ar/teach/concern');
  await expect(page.locator('h1')).toHaveText('بلّغ عن قلق على أمان طالب');
  const form = page.locator('form');
  await form.getByLabel('إيه اللي حصل؟').fill('الطالب قال في الحصة إن حد بيضايقه على التطبيق.');
  // What it is about is asked for first.
  await form.getByRole('button', { name: 'ابعت لمسؤولين الحماية' }).click();
  await expect(form.getByText('اختار الموضوع عن إيه.')).toBeVisible();
  await form.locator('label:has(input[name="category"])').first().click();
  await form.getByLabel('أنهي طالب؟').selectOption({ index: 1 });
  await form.getByRole('button', { name: 'ابعت لمسؤولين الحماية' }).click();
  await expect(page.getByText(/^رقم #?\d+$/).first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'بلّغ عن حاجة تانية' })).toBeVisible();
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

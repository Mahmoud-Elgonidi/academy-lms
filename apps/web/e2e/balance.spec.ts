import type { BrowserContext, Page } from '@playwright/test';
import { trialAfter, type TrialAfter } from './backend';
import { KID, PASSWORD, STUDENT, expect, fitsTheScreen, noRawKeys, signInWithPassword, test } from './helpers';

/*
 * A clear balance and adding a child (docs/architecture/02, section 27, J5a):
 * each course's card on the classic home (lessons left as a bar with the mark
 * where the next invoice goes out, the plan, the package's invoice waiting
 * with its day and the way to pay it on its own page), a child's few words
 * with a dot a lesson, a student's card without money, the money page's
 * invoices each opening their page, and a guardian adding a child from the
 * start flow (the consent wording with the name typed, a refusal under its
 * field) and landing back in the flow for them.
 *
 * The demo's Hala (DemoCollectionSeeder) has a family invoice for her two
 * children's next packages; the end-to-end reset puts it due within a day
 * (E2eCollection), so it reads as soon at whatever hour the tests run.
 */

const HALA = process.env.E2E_COLLECTION_GUARDIAN ?? 'hala.guardian@demo.test';

test.describe.configure({ mode: 'serial' });

test.beforeEach(async ({ page }) => {
  await page.setExtraHTTPHeaders({ 'X-Forwarded-For': `10.${Math.floor(Math.random() * 250)}.${Math.floor(Math.random() * 250)}.${Math.floor(Math.random() * 250) + 1}` });
});

/** Signing in with the demo password, then the family's home (the first page may take a moment to build). */
async function signIn(page: Page, login: string) {
  await signInWithPassword(page, login, PASSWORD);
  await expect(page).toHaveURL(/\/ar\/family$/, { timeout: 20_000 });
}

async function signedIn(context: BrowserContext, token: string, baseURL: string | undefined) {
  await context.addCookies([{ name: 'lms_session', value: token, url: baseURL ?? 'http://127.0.0.1:3000', httpOnly: true, sameSite: 'Lax' }]);
}

test('a guardian reads each course balance and pays the invoice due tomorrow from its card', async ({ page }) => {
  await signIn(page, HALA);

  const card = page.locator('[data-balance="classic"]').first();
  await expect(card).toBeVisible();
  // The lessons left in words and as a bar out of the package, with the mark where the next invoice goes out.
  await expect(card.locator('[data-lessons-left]')).toHaveText(/^\d+$/);
  await expect(card.getByRole('meter')).toHaveAttribute('aria-valuemax', /\d+/);
  await expect(card.locator('[data-mark]')).toContainText('تصدر الفاتورة التالية عندما');
  await expect(card.locator('[data-plan]')).toBeVisible();
  // At or under the mark with the package's invoice waiting: it says so, and the invoice's day, soon.
  await expect(card).toHaveAttribute('data-state', /low|empty/);
  const waiting = card.locator('[data-row="waiting"]');
  await expect(waiting).toContainText('تنتظر الدفع');
  await expect(waiting).toHaveAttribute('data-due', /soon|overdue/);
  await expect(waiting.locator('[data-due-label]')).toContainText('موعد الدفع:');
  await fitsTheScreen(page);
  await noRawKeys(page);

  // "Pay" opens that invoice's own page.
  await waiting.locator('[data-pay-invoice]').click();
  await expect(page).toHaveURL(/\/ar\/family\/invoices\/[0-9a-f-]{36}$/);
  await expect(page.getByRole('heading', { level: 1 })).toContainText('NR-');
  await expect(page.locator('#inv-pay')).toHaveText('الدفع');
});

test('the money page opens each invoice, and the course page has the card', async ({ page }) => {
  await signIn(page, HALA);
  await page.goto('/ar/family/money');
  const rows = page.locator('[data-invoice-row]');
  await expect(rows.first()).toBeVisible();
  // A paid one (its badge sits under the row): the whole row opens its page.
  const paid = page.locator('li').filter({ has: page.getByText('مدفوعة', { exact: true }) }).locator('[data-invoice-row]').first();
  const id = await paid.getAttribute('data-invoice-row');
  await paid.click();
  await expect(page).toHaveURL(new RegExp(`/ar/family/invoices/${id}$`));
  await expect(page.locator('[data-paid]')).toBeVisible();

  // The balance's details on the money page lead to the course's own card.
  await page.goto('/ar/family/money');
  await page.locator('[data-balance-details]').first().click();
  await expect(page).toHaveURL(/\/ar\/family\/progress\/[0-9a-f-]{36}#balance-/);
  await expect(page.locator('[data-balance="classic"]')).toBeVisible();
  await noRawKeys(page);
});

test('a child reads their lessons left in a few words, with no money', async ({ page }) => {
  await signIn(page, KID);
  const card = page.locator('[data-balance="kids"]').first();
  await expect(card).toBeVisible();
  await expect(card.locator('[data-lessons-text]')).toHaveText(/حص|لا توجد/);
  await expect(card).not.toContainText(/ر\.س|فاتورة|ادفع/);
  await expect(page.locator('[data-pay-invoice], [data-row="waiting"], [role="meter"]')).toHaveCount(0);
  await fitsTheScreen(page);
  await noRawKeys(page);
});

test('a middle-school student reads the bar and the plan, without money', async ({ page }) => {
  await signIn(page, STUDENT);
  const card = page.locator('[data-balance="student"]').first();
  await expect(card).toBeVisible();
  await expect(card.getByRole('meter')).toBeVisible();
  await expect(card.locator('[data-mark]')).toContainText('تتجدّد الباقة');
  await expect(card).not.toContainText(/ر\.س|ادفع/);
  await expect(page.locator('[data-pay-invoice]')).toHaveCount(0);
});

test.describe('adding a child', () => {
  let family: TrialAfter;
  const name = `سلمى ${['النجار', 'الحسيني', 'العطار', 'الخطيب', 'الشامي'][Math.floor(Math.random() * 5)]} ${['أحمد', 'محمود', 'يوسف', 'خالد'][Math.floor(Math.random() * 4)]}`;

  test.beforeAll(() => {
    // A guardian new to this run, with one child: the start flow has no "whose course" step, so the way to add one is a line.
    family = trialAfter();
  });

  test('from the start flow, with consent, then straight back into it for the child', async ({ page, context, baseURL }) => {
    await signedIn(context, family.guardian_token, baseURL);
    await page.goto('/ar/family/start');
    await page.locator('[data-add-child-entry] a').click();
    await expect(page).toHaveURL(/\/ar\/family\/children\/new\?from=start$/);
    await expect(page.getByRole('heading', { level: 1, name: 'من ستضيف إلى حسابك؟' })).toBeVisible();

    // Nothing filled: each field says what it needs, nothing is sent.
    await page.locator('[data-child-submit]').click();
    await expect(page.getByText('اكتب اسم الطفل (حرفان على الأقل).')).toBeVisible();
    await expect(page.getByText('ضع علامة على الموافقة أولًا.')).toBeVisible();

    // The consent wording carries the name as it is typed.
    await page.locator('[data-child-name]').fill(name);
    await expect(page.locator('[data-child-consent]')).toContainText(name);
    await page.locator('[data-child-year]').selectOption(String(new Date().getUTCFullYear() - 8));
    await page.getByText('بنت', { exact: true }).click();
    await page.locator('[data-child-agree]').check();
    await noRawKeys(page);
    await fitsTheScreen(page);
    await page.locator('[data-child-submit]').click();

    // Straight into the start flow for her: two learners now, she is the one chosen, so the subject comes next.
    await expect(page).toHaveURL(/\/ar\/family\/start\?learner=[0-9a-f-]{36}/);
    await expect(page.getByRole('heading', { level: 2, name: 'اختر المادة' })).toBeVisible();
    await expect(page.locator('h1')).toContainText(name.split(' ')[0]!);

    // Back to the first step: both children, and the card to add another.
    await page.goto('/ar/family/start?step=learner');
    await expect(page.locator('[data-learner]')).toHaveCount(2);
    await expect(page.locator('[data-add-child-entry]')).toBeVisible();
  });

  test('the same child again is refused under the name, kindly', async ({ page, context, baseURL }) => {
    await signedIn(context, family.guardian_token, baseURL);
    await page.goto('/ar/family/children/new');
    await page.locator('[data-child-name]').fill(name);
    await page.locator('[data-child-year]').selectOption(String(new Date().getUTCFullYear() - 8));
    await page.locator('[data-child-agree]').check();
    await page.locator('[data-child-submit]').click();
    await expect(page.getByText(/في حسابكم طفل بالاسم نفسه وسنة الميلاد نفسها/)).toBeVisible();
    await expect(page).toHaveURL(/\/ar\/family\/children\/new$/);
    await expect(page.locator('[data-child-name]')).toHaveAttribute('aria-invalid', 'true');
  });

  test('a child in their own look is not offered it', async ({ page, context, baseURL }) => {
    await signedIn(context, family.learner_token, baseURL);
    await page.goto('/ar/family/children/new');
    await expect(page.locator('[data-add-child="closed"]')).toBeVisible();
  });
});

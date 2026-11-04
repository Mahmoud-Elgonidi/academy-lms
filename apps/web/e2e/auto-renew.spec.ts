import { endTestStarts, freeTestTrials, renewalChargedNow, renewalComing, trialAfter, type TrialAfter } from './backend';
import { expect, fitsTheScreen, noRawKeys, test } from './helpers';
import { agree, confirm, fromReport, oneLessonAWeek, payOnTheDemoGateway, signedIn, teacherAndTimes, toReview } from './start-flow';

/*
 * The saved card and automatic renewal (docs/architecture/02, section 27,
 * J5b): a guardian new to the run starts a course and pays its first invoice
 * on the demo gateway with "save my card and renew automatically" ticked
 * (the words they agree to open with it); back on /pay/return the card is
 * said to be kept, with the way to manage it; the card page shows the card
 * (Visa ****4242, 12/2034) and renewal on. The course's next package then
 * falls due: the payer is told, the card page and the course's balance show
 * the charge coming with its moment and amount; the charge goes through on
 * the demo gateway and the package's lessons are funded. Then renewal is
 * turned off (what happens said first) and on again, and the card removed
 * (said first too), leaving the page empty with how to save one next time.
 */

test.describe.configure({ mode: 'serial' });

let family: TrialAfter;
let enrollment = '';
let renewal = { invoice: '', number: '', charge: null as string | null };

test.beforeAll(() => {
  // Earlier runs' courses end and their families' trials to come are cancelled first: the demo's teachers keep their weekly room.
  endTestStarts();
  freeTestTrials();
  family = trialAfter();
});

test.afterAll(() => {
  endTestStarts();
});

test.beforeEach(async ({ page }) => {
  await page.setExtraHTTPHeaders({ 'X-Forwarded-For': `10.${Math.floor(Math.random() * 250)}.${Math.floor(Math.random() * 250)}.${Math.floor(Math.random() * 250) + 1}` });
});

test('paying with "save my card" ticked keeps the card and turns automatic renewal on', async ({ page, context, baseURL }) => {
  await signedIn(context, family.guardian_token, baseURL);
  // Before any card: the card page says how one is saved.
  await page.goto('/ar/family/money/cards');
  await expect(page.locator('[data-cards-empty]')).toContainText('لا توجد بطاقة محفوظة');
  await noRawKeys(page);

  await fromReport(page, family);
  await oneLessonAWeek(page);
  await teacherAndTimes(page);
  await toReview(page);
  await confirm(page);
  await agree(page);
  await expect(page).toHaveURL(/\/ar\/family\/invoices\/[0-9a-f-]{36}\?course=[0-9a-f-]{36}&started=1/);
  enrollment = new URL(page.url()).searchParams.get('course')!;

  // The box beside paying: unticked at first, the words to agree to a tap away, open once ticked.
  const save = page.locator('[data-pay] [data-save-card]');
  await expect(save).toHaveAttribute('data-save-card', 'off');
  await expect(save.getByText('احفظ بطاقتي وجدّد تلقائيًا')).toBeVisible();
  await expect(save.locator('[data-mandate-text]')).toBeHidden();
  await save.getByRole('checkbox').check();
  await expect(save).toHaveAttribute('data-save-card', 'on');
  await expect(save.locator('[data-mandate-text]')).toContainText('تحفظ بوابة الدفع بطاقتي');
  await fitsTheScreen(page);
  await noRawKeys(page);

  await payOnTheDemoGateway(page, baseURL);
  const saved = page.locator('[data-card-saved]');
  await expect(saved.getByRole('heading', { name: 'حُفظت بطاقتك وشُغّل التجديد التلقائي' })).toBeVisible();
  await fitsTheScreen(page);
  await saved.locator('[data-manage-cards]').click();

  await expect(page).toHaveURL(/\/ar\/family\/money\/cards$/);
  await expect(page.getByRole('heading', { level: 1, name: 'البطاقات والتجديد التلقائي' })).toBeVisible();
  const card = page.locator('[data-card]');
  await expect(card).toHaveCount(1);
  await expect(card.locator('[data-card-brand]')).toHaveText('Visa');
  await expect(card.locator('[data-card-last4]')).toContainText('4242');
  await expect(card.locator('[data-card-expiry]')).toHaveText('12/2034');
  await expect(page.locator('[data-auto-renew-switch]')).toHaveAttribute('aria-checked', 'true');
  await expect(page.locator('[data-renewal-text]')).toContainText('****4242');
  await fitsTheScreen(page);
  await noRawKeys(page);

  // The money page leads here, saying renewal is on.
  await page.goto('/ar/family/money');
  await expect(page.locator('[data-cards-entry="on"]')).toContainText('التجديد التلقائي يعمل');
});

test('the next package falls due: told ahead, charged to the card, and its lessons funded', async ({ page, context, baseURL }) => {
  await signedIn(context, family.guardian_token, baseURL);
  await page.goto(`/ar/family/progress/${enrollment}`);
  const balance = page.locator(`#balance-${enrollment}`);
  await expect(balance.locator('[data-row="auto-renew"]')).toHaveAttribute('data-on', 'on');
  const before = Number(await balance.locator('[data-lessons-left]').textContent());

  renewal = renewalComing(enrollment);
  expect(renewal.charge).toBe('scheduled');

  // The card page and the course's balance show the charge coming: which invoice, how much, when.
  await page.goto('/ar/family/money/cards');
  const coming = page.locator('[data-charge="scheduled"]');
  await expect(coming).toContainText(renewal.number);
  await expect(coming).toContainText('يُخصم في');
  await page.goto(`/ar/family/progress/${enrollment}`);
  await expect(balance.locator('[data-next-charge="scheduled"]')).toContainText('يُخصم');
  // The package's invoice waits, but the card pays it: said so, and paying by hand stays there, quietly.
  await expect(balance.locator('[data-row="waiting"] [data-card-pays]')).toBeVisible();
  await fitsTheScreen(page);
  await noRawKeys(page);

  // Its moment comes: the demo gateway charges the card, and the package's lessons are funded.
  expect(renewalChargedNow(renewal.invoice)).toBe('paid');
  await page.reload();
  await expect.poll(async () => Number(await balance.locator('[data-lessons-left]').textContent())).toBeGreaterThan(before);
  await expect(balance.locator('[data-next-charge]')).toHaveCount(0);
  await page.goto('/ar/family/money/cards');
  await expect(page.locator('[data-charge]')).toHaveCount(0);
  await page.goto(`/ar/family/invoices/${renewal.invoice}`);
  await expect(page.locator('[data-paid]')).toBeVisible();
});

test('renewal turned off (said first) and on again, then the card removed (said first)', async ({ page, context, baseURL }) => {
  await signedIn(context, family.guardian_token, baseURL);
  await page.goto('/ar/family/money/cards');
  const toggle = page.locator('[data-auto-renew-switch]');
  await expect(toggle).toHaveAttribute('aria-checked', 'true');

  // Off: what happens is said first; keeping it changes nothing.
  await toggle.click();
  let dialog = page.getByRole('dialog', { name: 'إيقاف التجديد التلقائي؟' });
  await expect(dialog.getByText('تصلك كل فاتورة تجديد برابط دفعها، وتدفعها بنفسك قبل موعدها.')).toBeVisible();
  await dialog.getByRole('button', { name: 'أبقِه يعمل' }).click();
  await expect(toggle).toHaveAttribute('aria-checked', 'true');
  await toggle.click();
  await dialog.getByRole('button', { name: 'نعم، أوقفه' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'أُوقف التجديد التلقائي' })).toBeVisible();
  await expect(toggle).toHaveAttribute('aria-checked', 'false');
  await expect(page.locator('[data-renewal="off"]')).toBeVisible();

  // On again, in one tap.
  await toggle.click();
  await expect(page.getByRole('status').filter({ hasText: 'شُغّل التجديد التلقائي' })).toBeVisible();
  await expect(toggle).toHaveAttribute('aria-checked', 'true');

  // Removing the card: renewal goes off with it, said first.
  await page.locator('[data-remove-card]').click();
  dialog = page.getByRole('dialog', { name: 'حذف البطاقة Visa ****4242؟' });
  await expect(dialog.getByText('يتوقف التجديد التلقائي، ويُلغى كل خصم لم يحن موعده.')).toBeVisible();
  await dialog.getByRole('button', { name: 'نعم، احذفها' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'حُذفت البطاقة' })).toBeVisible();
  await expect(page.locator('[data-cards-empty]')).toBeVisible();
  await expect(page.locator('[data-card]')).toHaveCount(0);
  await fitsTheScreen(page);
  await noRawKeys(page);

  // The course's balance says renewal is off, with how to turn it on.
  await page.goto(`/ar/family/progress/${enrollment}`);
  await expect(page.locator(`#balance-${enrollment} [data-row="auto-renew"]`)).toHaveAttribute('data-on', 'off');
});

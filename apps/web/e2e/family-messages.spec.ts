import { expect, fitsTheScreen, test } from './helpers';

/*
 * Messages with the teachers: a child in the kids' look writes to their
 * teacher (their family reads it too), and a phone number in it is hidden
 * before anyone reads it, said in a child's words.
 */
test.describe('a kid (Yousef) and their teacher', () => {
  test.use({ storageState: 'e2e/.auth/kid.json' });

  test('writes to the teacher; a number in it is hidden, and the page says so', async ({ page }) => {
    await page.goto('/ar/family/messages');
    await expect(page.locator('h1')).toHaveText('تحدّث مع معلّمك');
    await expect(page.getByText('أبوك وأمك يرون الرسائل')).toBeVisible();
    await fitsTheScreen(page);

    await page.locator('main li').first().locator('a, button').first().click();
    await expect(page).toHaveURL(/\/ar\/family\/messages\/[0-9a-f-]+$/);
    const body = page.locator('textarea[name="body"]');
    await body.fill('يا أستاذة هذا رقم أبي 01001234567');
    await body.press('Enter');

    const mine = page.locator('ol li').filter({ hasText: 'هذا رقم أبي' }).last();
    await expect(mine).toContainText('[•••]');
    await expect(mine).not.toContainText('01001234567');
    await expect(mine).toContainText('أخفينا رقم هاتف');
    await fitsTheScreen(page);
  });
});

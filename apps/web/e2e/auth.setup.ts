import { expect, test as setup } from '@playwright/test';
import { GUARDIAN, KID, PASSWORD, STUDENT, TEACHER, signInWithPassword } from './helpers';

// One signed-in teacher for the teacher tests (the session lives in an httpOnly cookie).
setup('sign in as the demo teacher', async ({ page }) => {
  await signInWithPassword(page, TEACHER, PASSWORD);
  await expect(page).toHaveURL(/\/ar\/teach$/);
  await page.context().storageState({ path: 'e2e/.auth/teacher.json' });
});

// A guardian and two students in their own looks, each signed in once for the family tests.
for (const [name, login] of [['guardian', GUARDIAN], ['kid', KID], ['student', STUDENT]] as const) {
  setup(`sign in as the demo ${name}`, async ({ page }) => {
    await signInWithPassword(page, login, PASSWORD);
    await expect(page).toHaveURL(/\/ar\/family$/);
    await page.context().storageState({ path: `e2e/.auth/${name}.json` });
  });
}

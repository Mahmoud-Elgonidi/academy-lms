import { expect, test as setup } from '@playwright/test';
import { PASSWORD, TEACHER, signInWithPassword } from './helpers';

// One signed-in teacher for the teacher tests (the session lives in an httpOnly cookie).
setup('sign in as the demo teacher', async ({ page }) => {
  await signInWithPassword(page, TEACHER, PASSWORD);
  await expect(page).toHaveURL(/\/ar\/teach$/);
  await page.context().storageState({ path: 'e2e/.auth/teacher.json' });
});

import { fixtures } from './backend';
import { PASSWORD, expect, signInWithPassword, test as setup } from './helpers';

// The demo group's people (E2eResetSeeder's `group`): a guardian of a child in it, a student in it and its teacher, each signed in once.
for (const who of ['guardian', 'student', 'teacher'] as const) {
  setup(`sign in as the demo group's ${who}`, async ({ page }) => {
    await signInWithPassword(page, fixtures().group[who], PASSWORD);
    await expect(page).toHaveURL(who === 'teacher' ? /\/ar\/teach$/ : /\/ar\/family$/);
    await page.context().storageState({ path: `e2e/.auth/group-${who}.json` });
  });
}

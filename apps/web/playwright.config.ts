import { defineConfig, devices } from '@playwright/test';

/**
 * The portal against a running API with the demo data (`php artisan
 * migrate:fresh --seed` as lms_owner, then `php artisan serve`), as a teacher
 * or a family would use it. The tests change that data (a report sent,
 * homework marked and handed in, a reward collected), so they run one at a
 * time, on a fresh demo in CI.
 */
const base = process.env.WEB_URL ?? 'http://127.0.0.1:3000';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  timeout: 45_000,
  use: {
    baseURL: base,
    locale: 'ar-EG',
    timezoneId: 'Africa/Cairo',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'setup', testMatch: /auth\.setup\.ts/ },
    { name: 'sign-in', testMatch: /sign-in\.spec\.ts/, use: { ...devices['Desktop Chrome'] } },
    {
      name: 'teacher',
      testMatch: /teacher\.spec\.ts/,
      dependencies: ['setup'],
      use: { ...devices['Pixel 7'], storageState: 'e2e/.auth/teacher.json' },
    },
    // Each family spec picks its account (test.use storageState).
    { name: 'family', testMatch: /family-.*\.spec\.ts/, dependencies: ['setup'], use: { ...devices['Pixel 7'] } },
  ],
  webServer: process.env.E2E_SERVE
    ? { command: 'npm run start', url: `${base}/ar/sign-in`, reuseExistingServer: true, timeout: 120_000 }
    : undefined,
});

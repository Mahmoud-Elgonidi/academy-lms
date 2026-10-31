import { defineConfig, devices } from '@playwright/test';

/**
 * The portal against a running API with the demo data (`php artisan
 * migrate --database=pgsql_owner` and `php artisan db:seed`, then `php
 * artisan serve`), as a teacher or a family would use it. The tests change
 * that data (a report sent, homework marked and handed in, a chest opened, a
 * receipt approved), so they run one at a time, and the global setup puts
 * back what they use up first (the API's E2eResetSeeder, through artisan in
 * E2E_API_DIR with this process's environment: run the tests with the
 * API's DB_DATABASE and the rest). Every test fails, never skips, when the
 * state it needs is missing.
 *
 * The phone projects (a Pixel 7) take every flow; `desktop` takes each
 * look's pages again at 1366px, where the side menu replaces the tab bar.
 */
const base = process.env.WEB_URL ?? 'http://127.0.0.1:3000';
const desktop = { ...devices['Desktop Chrome'], viewport: { width: 1366, height: 768 } };

export default defineConfig({
  testDir: './e2e',
  globalSetup: './e2e/global-setup.ts',
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
    { name: 'sign-in', testMatch: /sign-in\.spec\.ts/, use: desktop },
    {
      name: 'teacher',
      testMatch: /teacher\.spec\.ts/,
      dependencies: ['setup'],
      use: { ...devices['Pixel 7'], storageState: 'e2e/.auth/teacher.json' },
    },
    // Each family spec picks its account (test.use storageState).
    { name: 'family', testMatch: /family-.*\.spec\.ts/, dependencies: ['setup'], use: { ...devices['Pixel 7'] } },
    { name: 'desktop', testMatch: /desktop\.spec\.ts/, dependencies: ['setup'], use: desktop },
    // Group lessons (the demo group): its family and student signed in apart, the teacher as above.
    { name: 'groups-setup', testMatch: /groups\.setup\.ts/ },
    { name: 'groups', testMatch: /groups\.spec\.ts/, dependencies: ['setup', 'groups-setup'], use: { ...devices['Pixel 7'] } },
    // The pages before signing in (the academy's, the platform's), on a phone.
    { name: 'site', testMatch: /site\.spec\.ts/, dependencies: ['setup'], use: { ...devices['Pixel 7'] } },
  ],
  webServer: process.env.E2E_SERVE
    ? { command: 'npm run start', url: `${base}/ar/sign-in`, reuseExistingServer: true, timeout: 120_000 }
    : undefined,
});

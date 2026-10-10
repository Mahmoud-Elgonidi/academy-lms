/**
 * The demo accounts the lessons are filmed with. Passwords and authenticator
 * secrets never live here: COURSE_PASSWORD is the demo accounts' password,
 * COURSE_TOTP_* an account's authenticator secret, and the platform team's
 * code comes from `php artisan platform:code` (local demo only).
 */
export const USERS = {
  owner: { email: 'owner@demo.test', panel: 'admin', totpEnv: 'COURSE_TOTP_OWNER' },
  accountant: { email: 'accountant@demo.test', panel: 'admin', totpEnv: 'COURSE_TOTP_ACCOUNTANT' },
  teacher: { email: 'nour@demo.test', panel: 'teach' },
  teacher2: { email: 'khaled@demo.test', panel: 'teach' },
  guardian: { email: 'salma.guardian@demo.test', panel: 'my' },
  kid: { email: 'yousef@demo.test', panel: 'my' },
  middle: { email: 'ziad@demo.test', panel: 'my' },
  teen: { email: 'maryam@demo.test', panel: 'my' },
  platform: { email: 'admin@platform.test', panel: 'console', codeCommand: ['php', 'artisan', 'platform:code', 'admin@platform.test'] },
};

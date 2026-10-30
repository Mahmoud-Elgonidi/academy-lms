/**
 * The signed-in person on this academy's host: an API token kept in an
 * httpOnly cookie, so the browser never sees it (the BFF of
 * docs/architecture/03). Cookies belong to the host, so each academy's
 * domain has its own session.
 */
export const SESSION_COOKIE = 'lms_session';

/** The viewer's IANA zone, written by the page itself (Intl), sent as X-Timezone. */
export const TIMEZONE_COOKIE = 'lms_tz';

/**
 * The zone the person chose for their account on this browser (the zone chip,
 * My account), sent as X-Timezone instead of the device's, so the pages show
 * the clock they chose. Gone when they go back to their device's zone, sign in
 * or out, or when the account's zone changed elsewhere (the chip lets go of it).
 */
export const CHOSEN_ZONE_COOKIE = 'lms_tz_chosen';

export type Role = 'owner' | 'admin' | 'scheduler' | 'accountant' | 'sales' | 'supervisor' | 'teacher' | 'guardian' | 'student';

export const STAFF_ROLES: readonly Role[] = ['owner', 'admin', 'scheduler', 'accountant', 'sales', 'supervisor'];

/** Where a person's portal starts: teachers teach; families go to their area; staff use the back office. */
export function homeOf(roles: readonly string[]): 'teach' | 'family' | 'staff' {
  if (roles.includes('teacher')) return 'teach';
  if (roles.includes('guardian') || roles.includes('student')) return 'family';
  return 'staff';
}

/** A path inside this portal (and its locale), safe to send someone back to after signing in. */
export function safeNext(next: string | null | undefined, locale: string): string | null {
  if (!next || !next.startsWith(`/${locale}/`) || next.startsWith('//') || next.includes('\\')) return null;
  return next.startsWith(`/${locale}/sign-in`) ? null : next;
}

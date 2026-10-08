import type { LocalTime } from './time';

/**
 * Signing in with Google or Apple in the portal (docs/architecture/02): the
 * browser leaves from /bff/social/{provider}/start, which keeps a secret in
 * an httpOnly cookie and sends only its hash to the API; Google or Apple
 * send it back through the platform to /bff/social/finish with a ticket that
 * works once, for that secret.
 */
export const SOCIAL_COOKIE = 'lms_social';

/** A sign-in waiting for the authenticator's code after Google or Apple (the sign-in page picks it up). */
export const TWO_FACTOR_COOKIE = 'lms_two_factor';

export type SocialProvider = { provider: string; label: string; web: boolean; app: boolean };

export type SocialLink = {
  provider: string;
  label: string;
  linked: boolean;
  email: string | null;
  linked_at: LocalTime | null;
  last_used_at: LocalTime | null;
};

/** What a sign-in page shows about the way back ("?social=…"). */
export const SOCIAL_OUTCOMES = ['denied', 'failed', 'expired', 'no_account', 'held', 'off'] as const;
export type SocialOutcome = (typeof SOCIAL_OUTCOMES)[number];

export function outcomeOf(value: string | null | undefined): SocialOutcome | null {
  return (SOCIAL_OUTCOMES as readonly string[]).includes(value ?? '') ? (value as SocialOutcome) : null;
}

/** The API's refusal of a ticket, as one of the outcomes the sign-in page explains. */
export function refusalOf(type: string | undefined): SocialOutcome {
  if (type === 'social-account-not-found') return 'no_account';
  if (type === 'guardian-consent-required') return 'held';
  if (type === 'social-ticket-refused') return 'expired';
  return 'failed';
}

/** Where a button starts: to sign in (back to `next` after), or to link this account (back to `back`). */
export function startHref(provider: string, locale: string, options: { link?: boolean; back?: string } = {}): string {
  const query = new URLSearchParams({ locale });
  if (options.link) query.set('link', '1');
  if (options.back) query.set('back', options.back);
  return `/bff/social/${encodeURIComponent(provider)}/start?${query.toString()}`;
}

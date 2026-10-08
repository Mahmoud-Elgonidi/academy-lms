import type { Problem } from './api/server';

/** Where a refused sign-in goes when the refusal is about two-step sign-in (auth/two-factor). */
export type TwoFactorTurn = { step: 'two-factor'; twoFactorToken: string } | { step: 'back-office' };

/**
 * The password or code was right and the account has two-step sign-in on:
 * the authenticator's code comes next, with the API's token. A back-office
 * role that has not set it up yet ("two-factor-setup-required") sets it up
 * in the back office, which is where it works anyway. Anything else is an
 * ordinary refusal (null).
 */
export function twoFactorTurn(problem: Pick<Problem, 'type' | 'extra'>): TwoFactorTurn | null {
  if (problem.type === 'two-factor-setup-required') return { step: 'back-office' };
  const token = problem.extra?.two_factor_token;
  return problem.type === 'two-factor-required' && typeof token === 'string' && token !== '' ? { step: 'two-factor', twoFactorToken: token } : null;
}

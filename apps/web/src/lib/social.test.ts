import { describe, expect, it } from 'vitest';
import { outcomeOf, refusalOf, startHref } from './social';

describe('signing in with Google or Apple', () => {
  it('starts from the portal with the language, and the way back when linking', () => {
    expect(startHref('google', 'ar')).toBe('/bff/social/google/start?locale=ar');
    expect(startHref('apple', 'en', { link: true, back: '/en/family/account' })).toBe('/bff/social/apple/start?locale=en&link=1&back=%2Fen%2Ffamily%2Faccount');
  });

  it('explains only the outcomes it knows', () => {
    expect(outcomeOf('no_account')).toBe('no_account');
    expect(outcomeOf('denied')).toBe('denied');
    expect(outcomeOf('linked')).toBeNull();
    expect(outcomeOf('<script>')).toBeNull();
    expect(outcomeOf(undefined)).toBeNull();
  });

  it("turns the API's refusal of a ticket into what the sign-in page says", () => {
    expect(refusalOf('social-account-not-found')).toBe('no_account');
    expect(refusalOf('guardian-consent-required')).toBe('held');
    expect(refusalOf('social-ticket-refused')).toBe('expired');
    expect(refusalOf('validation')).toBe('failed');
    expect(refusalOf(undefined)).toBe('failed');
  });
});

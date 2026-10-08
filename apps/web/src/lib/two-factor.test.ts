import { describe, expect, it } from 'vitest';
import { twoFactorTurn } from './two-factor';

describe('a sign-in refused for two-step sign-in', () => {
  it("goes on to the authenticator's code with the API's token", () => {
    expect(twoFactorTurn({ type: 'two-factor-required', extra: { two_factor_token: 'tf-1' } })).toEqual({ step: 'two-factor', twoFactorToken: 'tf-1' });
  });

  it('sends a back-office role that has not set it up to the back office', () => {
    expect(twoFactorTurn({ type: 'two-factor-setup-required', extra: {} })).toEqual({ step: 'back-office' });
  });

  it('leaves every other refusal alone', () => {
    expect(twoFactorTurn({ type: 'validation', extra: { two_factor_token: 'tf-1' } })).toBeNull();
    expect(twoFactorTurn({ type: 'two-factor-required', extra: {} })).toBeNull();
    expect(twoFactorTurn({ type: undefined, extra: undefined })).toBeNull();
  });
});

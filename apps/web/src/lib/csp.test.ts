import { describe, expect, it } from 'vitest';
import { BASE_POLICY, newNonce, pagePolicy } from './csp';

describe('the portal page policy', () => {
  it('lets only the request nonce and what it loads run, keeping the base set', () => {
    expect(pagePolicy('abc')).toBe(`${BASE_POLICY}; script-src 'self' 'nonce-abc' 'strict-dynamic'`);
    expect(pagePolicy('abc', true)).toContain("'unsafe-eval'");
    expect(pagePolicy('abc')).not.toContain('unsafe-inline');
  });

  it('makes a fresh nonce each time, fit for the header', () => {
    const nonces = new Set(Array.from({ length: 50 }, () => newNonce()));
    expect(nonces.size).toBe(50);
    for (const nonce of nonces) expect(nonce).toMatch(/^[A-Za-z0-9+/]+=*$/);
  });
});

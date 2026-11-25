import { afterEach, describe, expect, it, vi } from 'vitest';

// next-intl's getLocale() waits for the request config (i18n/request.ts), and that config asks for the
// academy's branding: if branding asked getLocale() in turn, every page would wait on itself forever.
vi.mock('server-only', () => ({}));
vi.mock('next/headers', () => ({
  cookies: async () => ({ get: () => undefined }),
  headers: async () => new Headers({ host: 'demo.lms.test' }),
}));
const getLocale = vi.fn(async () => 'ar');
vi.mock('next-intl/server', () => ({ getLocale: () => getLocale() }));

afterEach(() => {
  vi.unstubAllGlobals();
  getLocale.mockClear();
});

describe("the academy's branding for the request config", () => {
  it('asks the API in the language it is given, without waiting for the config', async () => {
    const fetcher = vi.fn(async () => Response.json({ data: { audience: 'adults' } }));
    vi.stubGlobal('fetch', fetcher);
    const { branding } = await import('./viewer');

    const look = await branding('en');

    expect(look.audience).toBe('adults');
    expect(getLocale).not.toHaveBeenCalled();
    const [, init] = fetcher.mock.calls[0] as unknown as [string, RequestInit];
    expect((init.headers as Record<string, string>)['Accept-Language']).toBe('en');
  });
});

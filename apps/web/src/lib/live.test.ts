import { describe, expect, it } from 'vitest';
import { momentsOf, needsFallback, socketOptions, waitUntilNext, type Realtime } from './live';

const realtime: Realtime = { enabled: true, key: 'k', host: null, port: 443, tls: true, channel: 'user.1' };

describe('the live socket', () => {
  it('goes to the page\'s own host unless the API names another', () => {
    expect(socketOptions(realtime, { hostname: 'noor.example.com', protocol: 'https:' })).toMatchObject({
      wsHost: 'noor.example.com',
      wsPort: 443,
      wssPort: 443,
      forceTLS: true,
      enabledTransports: ['ws', 'wss'],
    });
    expect(socketOptions({ ...realtime, host: 'ws.example.com' }, { hostname: 'noor.example.com', protocol: 'https:' }).wsHost).toBe('ws.example.com');
  });

  it('uses the plain port on a laptop', () => {
    expect(socketOptions({ ...realtime, port: 8080, tls: false }, { hostname: '127.0.0.1', protocol: 'http:' })).toMatchObject({ wsPort: 8080, forceTLS: false });
    expect(socketOptions({ ...realtime, port: null, tls: false }, { hostname: 'localhost', protocol: 'http:' }).wsPort).toBe(80);
  });
});

describe('what the clock alone changes', () => {
  const lesson = (starts: string, ends: string, opensAt: string | null, open = false) => ({
    starts_at: { utc: starts },
    ends_at: { utc: ends },
    join: { open, opens_at: opensAt ? { utc: opensAt } : null },
  });

  it('is the room opening, the lesson starting and the room closing ten minutes after the end', () => {
    expect(momentsOf([lesson('2026-10-08T18:00:00Z', '2026-10-08T18:30:00Z', '2026-10-08T17:50:00Z')])).toEqual([
      '2026-10-08T17:50:00Z',
      '2026-10-08T18:00:00Z',
      '2026-10-08T18:40:00.000Z',
    ]);
    expect(momentsOf([lesson('2026-10-08T18:00:00Z', '2026-10-08T18:30:00Z', null, true)])).toHaveLength(2);
  });

  it('waits for the soonest moment still ahead, a breath after it, and never more than six hours', () => {
    const now = Date.parse('2026-10-08T17:00:00Z');
    expect(waitUntilNext(['2026-10-08T16:00:00Z', '2026-10-08T17:50:00Z', '2026-10-08T17:30:00Z'], now)).toBe(30 * 60_000 + 2_000);
    expect(waitUntilNext(['2026-10-08T16:00:00Z'], now)).toBeNull();
    expect(waitUntilNext([], now)).toBeNull();
    expect(waitUntilNext(['2026-10-12T17:00:00Z'], now)).toBe(6 * 60 * 60_000);
    expect(waitUntilNext(['not a time'], now)).toBeNull();
  });
});

describe('the slow timer', () => {
  const now = Date.parse('2026-10-08T17:00:00Z');
  it('stays off while the socket is connected and the relay keeps saying it is alive', () => {
    expect(needsFallback({ connected: true, lastHeard: now - 60_000, lastRefresh: now - 10 * 60_000 }, now)).toBe(false);
  });
  it('refreshes once a minute without a socket, or when the relay went quiet', () => {
    expect(needsFallback({ connected: false, lastHeard: now, lastRefresh: now - 61_000 }, now)).toBe(true);
    expect(needsFallback({ connected: false, lastHeard: now, lastRefresh: now - 30_000 }, now)).toBe(false);
    expect(needsFallback({ connected: true, lastHeard: now - 151_000, lastRefresh: now - 61_000 }, now)).toBe(true);
  });
});

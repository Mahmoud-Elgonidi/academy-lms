import 'server-only';
import { cache } from 'react';
import { call, get } from './api/server';
import type { Realtime } from './live';

/**
 * What both portals' frames need to stay live: where the socket listens
 * (null when the API cannot say; the pages still work, refreshed by hand)
 * and how many messages are unread, for the badge.
 */
export const liveOf = cache(async (): Promise<{ realtime: Realtime | null; unread: number }> => {
  const [where, inbox] = await Promise.all([
    call('get', '/v1/realtime').then(async (r) => (r.ok ? ((await r.json()) as { data: Realtime }).data : null)).catch(() => null),
    get<{ meta: { unread: number } }>('/v1/notifications', { query: { per_page: 1 } }),
  ]);
  return { realtime: where, unread: inbox.meta.unread };
});

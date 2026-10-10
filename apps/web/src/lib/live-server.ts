import 'server-only';
import { cache } from 'react';
import { call, get } from './api/server';
import type { Realtime } from './live';

/**
 * What both portals' frames need to stay live: where the socket listens
 * (null when the API cannot say; the pages still work, refreshed by hand)
 * and how many notifications and conversation messages are unread, for the
 * badges.
 */
export const liveOf = cache(async (): Promise<{ realtime: Realtime | null; unread: number; chats: number }> => {
  const [where, inbox, chats] = await Promise.all([
    call('get', '/v1/realtime').then(async (r) => (r.ok ? ((await r.json()) as { data: Realtime }).data : null)).catch(() => null),
    get<{ meta: { unread: number } }>('/v1/notifications', { query: { per_page: 1 } }),
    get<{ data: { count: number } }>('/v1/messages/unread'),
  ]);
  return { realtime: where, unread: inbox.meta.unread, chats: chats.data.count };
});

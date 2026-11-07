import 'server-only';
import { cache } from 'react';
import { call, get, read } from './api/server';
import type { Realtime } from './live';
import { hasModule, modulesOf } from './modules';
import { me } from './viewer';

/**
 * What both portals' frames need to stay live: where the socket listens
 * (null when the API cannot say; the pages still work, refreshed by hand)
 * and how many notifications and conversation messages are unread, for the
 * badges. Messages are counted only while the academy has them on (and a
 * refusal counts none: the frame never fails for a badge).
 */
export const liveOf = cache(async (): Promise<{ realtime: Realtime | null; unread: number; chats: number }> => {
  const messaging = hasModule(modulesOf(await me()), 'messaging');
  const [where, inbox, chats] = await Promise.all([
    call('get', '/v1/realtime').then(async (r) => (r.ok ? ((await r.json()) as { data: Realtime }).data : null)).catch(() => null),
    get<{ meta: { unread: number } }>('/v1/notifications', { query: { per_page: 1 } }),
    messaging ? read<{ data: { count: number } }>('/v1/messages/unread') : Promise.resolve(null),
  ]);
  return { realtime: where, unread: inbox.meta.unread, chats: chats?.ok ? chats.data.data.count : 0 };
});

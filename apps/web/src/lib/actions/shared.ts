'use server';

import { refresh } from 'next/cache';
import { send } from '@/lib/api/server';
import type { JoinTicket } from '@/lib/teach/types';

/**
 * Writes every portal makes the same way, teachers and families alike: a
 * seat in a lesson (JoinLesson decides whose lesson it is and whether it is
 * open yet) and notifications marked read.
 */
export async function joinLesson(id: string): Promise<{ ok: true; ticket: JoinTicket } | { ok: false; message?: string }> {
  const joined = await send<{ data: JoinTicket }>('post', '/v1/lessons/{session}/join', { params: { session: id } });
  if (!joined.ok) return { ok: false, message: Object.values(joined.problem.errors)[0]?.[0] ?? (joined.problem.title || undefined) };
  return { ok: true, ticket: joined.data.data };
}

/** Marks notifications read: these, or all of them. */
export async function markRead(ids?: string[]): Promise<{ ok: boolean }> {
  const done = await send('post', '/v1/notifications/read', { body: ids ? { ids } : {} });
  if (done.ok) refresh();
  return { ok: done.ok };
}

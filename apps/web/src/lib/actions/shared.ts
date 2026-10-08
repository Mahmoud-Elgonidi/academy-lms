'use server';

import { refresh } from 'next/cache';
import { send } from '@/lib/api/server';
import type { JoinTicket } from '@/lib/teach/types';

/**
 * Writes every portal makes the same way, teachers and families alike: a
 * seat in a lesson (JoinLesson decides whose lesson it is and whether it is
 * open yet), notifications marked read, and tickets to the academy.
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

export type TicketResult = { ok: true; id?: string } | { ok: false; message?: string; errors?: Record<string, string[]> };

function failed(problem: { title?: string; errors: Record<string, string[]> }): TicketResult {
  return { ok: false, message: Object.values(problem.errors)[0]?.[0] ?? (problem.title || undefined), errors: problem.errors };
}

/** Writes to the academy (a family names the learner); one key per submit, so a retry opens it once. */
export async function openTicket(form: FormData, key: string): Promise<TicketResult> {
  const sent = new FormData();
  for (const name of ['learner_id', 'kind', 'subject', 'body', 'session_id']) {
    const value = String(form.get(name) ?? '').trim();
    if (value) sent.set(name, value);
  }
  const file = form.get('file');
  if (file instanceof File && file.size > 0) sent.set('file', file, file.name || 'attachment');
  const done = await send<{ data: { id: string } }>('post', '/v1/support/tickets', { form: sent, idempotencyKey: key });
  if (!done.ok) return failed(done.problem);
  refresh();
  return { ok: true, id: done.data.data.id };
}

/** Answers the academy on one of the person's tickets, with words, a picture or PDF, or both. */
export async function replyToTicket(ticket: string, form: FormData, key: string): Promise<TicketResult> {
  const sent = new FormData();
  const body = String(form.get('body') ?? '').trim();
  if (body) sent.set('body', body);
  const file = form.get('file');
  if (file instanceof File && file.size > 0) sent.set('file', file, file.name || 'attachment');
  const done = await send('post', '/v1/support/tickets/{ticket}/messages', { params: { ticket }, form: sent, idempotencyKey: key });
  if (!done.ok) return failed(done.problem);
  refresh();
  return { ok: true };
}

/** "It is not solved": reopens a resolved ticket within its week. */
export async function reopenTicket(ticket: string): Promise<TicketResult> {
  const done = await send('post', '/v1/support/tickets/{ticket}/reopen', { params: { ticket } });
  if (!done.ok) return failed(done.problem);
  refresh();
  return { ok: true };
}

/** Closes the person's own ticket: solved, or no longer needed. */
export async function closeTicket(ticket: string): Promise<TicketResult> {
  const done = await send('post', '/v1/support/tickets/{ticket}/close', { params: { ticket } });
  if (!done.ok) return failed(done.problem);
  refresh();
  return { ok: true };
}

'use server';

import { refresh } from 'next/cache';
import { read, send } from '@/lib/api/server';
import type { MushafPage, MushafResult } from '@/lib/mushaf/types';
import type { LessonRecording } from '@/lib/recordings';
import type { JoinTicket } from '@/lib/teach/types';
import type { TimeFormat } from '@/lib/time';

/**
 * Writes every portal makes the same way, teachers and families alike: a
 * seat in a lesson (JoinLesson decides whose lesson it is and whether it is
 * open yet), notifications marked read, tickets to the academy, lesson
 * recordings (consent, watching, starting and stopping one), unlinking
 * Google or Apple from one's account, disputing a lesson's outcome, a new
 * email or mobile number (confirmed by a code sent to it), and one's own
 * data (details put right, a copy, a correction, erasure, taking a request
 * back); how one reads a clock (12 or 24 hours, or the academy's); and a
 * lesson's shared mushaf read again (its teacher's page, or another a learner
 * looks at).
 */
export async function joinLesson(id: string): Promise<{ ok: true; ticket: JoinTicket; recording: LessonRecording } | { ok: false; message?: string }> {
  const joined = await send<{ data: JoinTicket; recording?: LessonRecording }>('post', '/v1/lessons/{session}/join', { params: { session: id } });
  if (!joined.ok) return { ok: false, message: Object.values(joined.problem.errors)[0]?.[0] ?? (joined.problem.title || undefined) };
  return { ok: true, ticket: joined.data.data, recording: joined.data.recording ?? { mode: 'off', controls: false, running: false, missing: [] } };
}

type Done = { ok: true } | { ok: false; message?: string };

function problemOf(problem: { title?: string; errors: Record<string, string[]> }): { ok: false; message?: string } {
  return { ok: false, message: Object.values(problem.errors)[0]?.[0] ?? (problem.title || undefined) };
}

/** Agrees to lessons being recorded, for oneself or one's child, to the wording's version shown. */
export async function agreeToRecording(person: string, version: number, agree: boolean): Promise<Done> {
  const done = await send('post', '/v1/recordings/consents/{person}', { params: { person }, body: { agree, version } });
  if (!done.ok) return problemOf(done.problem);
  refresh();
  return { ok: true };
}

/** Takes that consent back: a recording running stops, and nothing more is recorded. */
export async function withdrawRecordingConsent(person: string): Promise<Done> {
  const done = await send('post', '/v1/recordings/consents/{person}/withdraw', { params: { person } });
  if (!done.ok) return problemOf(done.problem);
  refresh();
  return { ok: true };
}

/** An address to play one recording (the opening is logged by the API). */
export async function watchRecording(id: string): Promise<{ ok: true; url: string; media: string } | { ok: false; message?: string }> {
  const done = await send<{ url: string; media: string }>('post', '/v1/recordings/{recording}/watch', { params: { recording: id } });
  if (!done.ok) return problemOf(done.problem);
  return { ok: true, url: done.data.url, media: done.data.media };
}

/** Starts or stops recording a lesson (its teacher, or a supervisor in it). */
export async function recordLesson(id: string, on: boolean): Promise<{ ok: true; running: boolean } | { ok: false; message?: string }> {
  const done = await send<{ data: { running: boolean } }>('post', on ? '/v1/lessons/{session}/recording' : '/v1/lessons/{session}/recording/stop', { params: { session: id } });
  if (!done.ok) return problemOf(done.problem);
  return { ok: true, running: done.data.data.running };
}

/** Takes Google or Apple off this account (not when it is the only way in). */
export async function unlinkSocial(provider: string): Promise<Done> {
  const done = await send('delete', '/v1/me/social/{provider}', { params: { provider } });
  if (!done.ok) return problemOf(done.problem);
  refresh();
  return { ok: true };
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

/**
 * Disputes what a lesson was recorded as: the family about the learner's
 * lesson, the teacher about their own. The same confirmation sends the same
 * key, so a retry disputes once.
 */
export async function disputeLesson(side: 'family' | 'teacher', id: string, reason: string, note: string, key: string): Promise<Done> {
  const options = { params: { session: id }, body: { reason, note: note.trim() }, idempotencyKey: key };
  const done = side === 'family' ? await send('post', '/v1/lessons/{session}/dispute', options) : await send('post', '/v1/teacher/lessons/{session}/dispute', options);
  if (!done.ok) return problemOf(done.problem);
  refresh();
  return { ok: true };
}

/** Asks for a copy of one's data, or a followed learner's (`subject`): prepared in a few minutes; one a day. */
export async function askDataCopy(subject: string, key: string): Promise<Done> {
  const done = await send('post', '/v1/me/data-requests', { body: { kind: 'export', subject_id: subject }, idempotencyKey: key });
  if (!done.ok) return problemOf(done.problem);
  refresh();
  return { ok: true };
}

/** Asks the academy to erase an account (one's own, or a followed learner's): it answers within 30 days. */
export async function askErasure(subject: string, reason: string, key: string): Promise<Done> {
  const done = await send('post', '/v1/me/data-requests', { body: { kind: 'erasure', subject_id: subject, reason: reason.trim() || null }, idempotencyKey: key });
  if (!done.ok) return problemOf(done.problem);
  refresh();
  return { ok: true };
}

/** Takes a correction back while the academy has not answered; an erasure until then, or in the day after it approved. */
export async function cancelDataRequest(id: string): Promise<Done> {
  const done = await send('post', '/v1/me/data-requests/{id}/cancel', { params: { id } });
  if (!done.ok) return problemOf(done.problem);
  refresh();
  return { ok: true };
}

/** Puts details right on the spot (one's own name; a student's details, by the student or a guardian): only what changed is sent. */
export async function correctDetails(subject: string, changes: Record<string, string | null>): Promise<{ ok: true; changed: string[] } | { ok: false; message?: string }> {
  const done = await send<{ data: { changed: string[] } }>('patch', '/v1/me/details/{subject}', { params: { subject }, body: changes });
  if (!done.ok) return problemOf(done.problem);
  refresh();
  return { ok: true, changed: done.data.data.changed };
}

/** Asks the academy to correct what cannot be corrected here: one open at a time, answered within 30 days. */
export async function askCorrection(subject: string, says: string, key: string): Promise<Done> {
  const done = await send('post', '/v1/me/data-requests', { body: { kind: 'correction', subject_id: subject, says: says.trim() }, idempotencyKey: key });
  if (!done.ok) return problemOf(done.problem);
  refresh();
  return { ok: true };
}

/**
 * How one reads a clock everywhere (the portal, the app, the panels, messages):
 * 12 hours (5:00 PM) or 24 (17:00), or null to follow the academy's. The frame
 * reads it again, so every page writes its clocks the new way.
 */
export async function saveTimeFormat(format: TimeFormat | null): Promise<Done> {
  if (format !== null && format !== 'h12' && format !== 'h24') return { ok: false };
  const done = await send('patch', '/v1/me', { body: { time_format: format } });
  if (!done.ok) return problemOf(done.problem);
  refresh();
  return { ok: true };
}

/** Sends a code to a new email or mobile number of one's own; nothing changes until the code comes back. */
export async function sendContactCode(
  kind: 'email' | 'phone',
  value: string,
): Promise<{ ok: true; challenge: string; sentTo: string; until: string } | { ok: false; message?: string }> {
  const done = await send<{ challenge_id: string; sent_to: string; expires_at: { label: string } }>('post', '/v1/me/contact', { body: { kind, value: value.trim() } });
  if (!done.ok) return problemOf(done.problem);
  return { ok: true, challenge: done.data.challenge_id, sentTo: done.data.sent_to, until: done.data.expires_at.label };
}

/** Confirms the new address with its code: it becomes the account's way in and where messages go. */
export async function confirmContact(challenge: string, code: string): Promise<Done> {
  const done = await send('post', '/v1/me/contact/confirm', { body: { challenge_id: challenge, code: code.trim() } });
  if (!done.ok) return problemOf(done.problem);
  refresh();
  return { ok: true };
}

/**
 * Tells the academy's safeguarding leads that something is wrong (a family
 * names the learner, a teacher may name a student). One key per submit, so a
 * retry raises it once.
 */
export async function raiseConcern(
  form: { learner_id?: string; category: string; text: string; urgent: boolean; session_id?: string; student_id?: string },
  key: string,
): Promise<{ ok: true; number: string; message: string } | { ok: false; message?: string; errors?: Record<string, string[]> }> {
  const body = {
    category: form.category,
    text: form.text.trim() || null,
    urgent: form.urgent,
    ...(form.learner_id ? { learner_id: form.learner_id } : {}),
    ...(form.session_id ? { session_id: form.session_id } : {}),
    ...(form.student_id ? { student_id: form.student_id } : {}),
  };
  const done = await send<{ data: { number: string } | null; message: string }>('post', '/v1/safeguarding/concerns', { body, idempotencyKey: key });
  if (!done.ok) {
    const problem = failed(done.problem);
    return problem.ok ? { ok: false } : problem;
  }
  refresh();
  return { ok: true, number: done.data.data?.number ?? '', message: done.data.message };
}

/** Opens the conversation with someone on my list (made the first time); a family names the learner. */
export async function openConversation(withId: string, learnerId?: string): Promise<{ ok: true; id: string } | { ok: false; message?: string }> {
  const done = await send<{ data: { id: string } }>('post', '/v1/messages/conversations', { body: { with_id: withId, learner_id: learnerId ?? null } });
  if (!done.ok) return problemOf(done.problem);
  return { ok: true, id: done.data.data.id };
}

/** Writes in a conversation; what was hidden from the words comes back, to say so. */
export async function sendChatMessage(conversation: string, body: string, key: string): Promise<{ ok: true; hidden: string | null } | { ok: false; message?: string }> {
  const done = await send<{ data: { hidden: string | null } }>('post', '/v1/messages/conversations/{conversation}/messages', {
    params: { conversation },
    body: { body },
    idempotencyKey: key,
  });
  if (!done.ok) return problemOf(done.problem);
  refresh();
  return { ok: true, hidden: done.data.data.hidden };
}

/**
 * A lesson's shared mushaf as it stands: the page asked for, else the one the
 * teacher has open. Only a read (the teacher's writes are the teacher's
 * actions); the screen draws what comes back.
 */
export async function readMushaf(session: string, page?: number): Promise<MushafResult> {
  const done = await read<{ data: MushafPage }>('/v1/learning/lessons/{session}/mushaf', { params: { session }, query: { page } });
  if (!done.ok) return problemOf(done.problem);
  return { ok: true, mushaf: done.data.data };
}

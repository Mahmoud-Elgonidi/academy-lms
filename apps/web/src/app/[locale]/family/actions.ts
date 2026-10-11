'use server';

import { refresh } from 'next/cache';
import { cookies, headers } from 'next/headers';
import { call, send, type Outcome } from '@/lib/api/server';
import { LEARNER_COOKIE } from '@/lib/family/context';
import type { CancelPreview, Rating } from '@/lib/family/types';
import type { OpenedChest } from '@/lib/journey/types';

/**
 * The family's writes, through the same endpoints (and so the same Actions)
 * as the app and the /my panel. The API checks each one again: whose lesson
 * or homework it is, the policy, that a kid neither cancels nor rates, and
 * that only the student opens their week's chest.
 */
export type Result = { ok: boolean; message?: string; errors?: Record<string, string[]> };

function result(outcome: Outcome<unknown>): Result {
  if (outcome.ok) {
    refresh();
    return { ok: true };
  }
  const first = Object.values(outcome.problem.errors)[0]?.[0];
  return { ok: false, message: first ?? (outcome.problem.title || undefined), errors: outcome.problem.errors };
}

/** Whose pages a guardian sees; kept for this browser. */
export async function chooseLearner(id: string): Promise<void> {
  const secure = ((await headers()).get('x-forwarded-proto') ?? 'http') === 'https';
  (await cookies()).set(LEARNER_COOKIE, id, { httpOnly: true, secure, sameSite: 'lax', path: '/', maxAge: 60 * 60 * 24 * 365 });
  refresh();
}

/** What cancelling would do by the academy's policy, and the reasons to pick from. */
export async function cancellationOf(id: string): Promise<{ ok: true; preview: CancelPreview } | { ok: false; message?: string }> {
  return read<CancelPreview>('/v1/lessons/{session}/cancellation', id);
}

/** This person's rating of a lesson, and the tags to offer. */
export async function ratingOf(id: string): Promise<{ ok: true; preview: Rating } | { ok: false; message?: string }> {
  const got = await read<{ data: Rating } | Rating>('/v1/lessons/{session}/rating', id);
  if (!got.ok) return got;
  const body = got.preview as { data?: Rating };
  return { ok: true, preview: body.data ?? (got.preview as Rating) };
}

/** A read a dialog asks for while it opens (the page itself is already there). */
async function read<T>(path: '/v1/lessons/{session}/cancellation' | '/v1/lessons/{session}/rating', id: string): Promise<{ ok: true; preview: T } | { ok: false; message?: string }> {
  const response = await call('get', path, { params: { session: id } });
  const body = (await response.json().catch(() => ({}))) as T & { title?: string; detail?: string };
  if (!response.ok) return { ok: false, message: body.detail ?? body.title };
  return { ok: true, preview: body };
}

/** Cancels a lesson; a retry of the same confirmation sends the same key and cancels once. */
export async function cancelLesson(id: string, reason: string, note: string, key: string): Promise<Result> {
  return result(
    await send('post', '/v1/lessons/{session}/cancel', {
      params: { session: id },
      body: { reason, note: note.trim() || null },
      idempotencyKey: key,
    }),
  );
}

/** Turns down the substitute: the lesson is cancelled on the academy's account, for a makeup. */
export async function refuseSubstitute(id: string): Promise<Result & { makeupDays?: number }> {
  const done = await send<{ makeup_days?: number }>('post', '/v1/lessons/{session}/refuse-substitute', { params: { session: id } });
  return done.ok ? { ...result(done), makeupDays: done.data.makeup_days } : result(done);
}

export async function rateLesson(id: string, stars: number, tags: string[], comment: string): Promise<Result> {
  return result(
    await send('put', '/v1/lessons/{session}/rating', {
      params: { session: id },
      body: { stars, tags, comment: comment.trim() || null },
    }),
  );
}

/** Hands homework in: words, a recording made in the browser, or a file (the API checks the kind and size). */
export async function handIn(id: string, form: FormData): Promise<Result> {
  const sent = new FormData();
  const body = String(form.get('body') ?? '').trim();
  if (body) sent.set('body', body);
  const file = form.get('file');
  if (file instanceof File && file.size > 0) sent.set('file', file, file.name || 'recording.webm');
  return result(await send('post', '/v1/learning/homework/{homework}/submissions', { params: { homework: id }, form: sent }));
}

/**
 * Opens the week's chest on the journey once every lesson of the week was
 * attended and every homework due in it handed in (the student only; the API
 * checks it again). One key per press, so a retry opens it once.
 */
export async function openChest(period: string, key: string): Promise<Result & { opened?: OpenedChest }> {
  const done = await send<{ data: OpenedChest }>('post', '/v1/learning/journey/chest', { body: { period }, idempotencyKey: key });
  return done.ok ? { ...result(done), opened: done.data.data } : result(done);
}

/** A guardian cheers their child on (a few a day). */
export async function cheer(learnerId: string, kind: string): Promise<Result> {
  return result(await send('post', '/v1/learning/cheers', { body: { learner_id: learnerId, kind } }));
}

export async function cheersSeen(): Promise<void> {
  await send('post', '/v1/learning/cheers/seen');
}

/** Opens a checkout at the academy's gateway; the page then goes there. */
export async function payInvoice(id: string, accountId: string | null, key: string): Promise<{ ok: true; url: string } | { ok: false; message?: string }> {
  const opened = await send<{ url: string }>('post', '/v1/family/invoices/{invoice}/checkout', {
    params: { invoice: id },
    body: accountId ? { account_id: accountId } : {},
    idempotencyKey: key,
  });
  if (!opened.ok) return { ok: false, message: Object.values(opened.problem.errors)[0]?.[0] ?? (opened.problem.title || undefined) };
  return { ok: true, url: opened.data.url };
}

/**
 * Sends proof of paying an invoice by hand: how, how much, the payer's own
 * day, the transfer's number and the receipt. It waits for the academy;
 * one key per submit, so a retry sends it once.
 */
export async function sendProof(id: string, form: FormData, key: string): Promise<Result> {
  const sent = new FormData();
  for (const name of ['method', 'amount', 'paid_on', 'reference', 'note']) {
    const value = String(form.get(name) ?? '').trim();
    if (value) sent.set(name, value);
  }
  const file = form.get('file');
  if (file instanceof File && file.size > 0) sent.set('file', file, file.name || 'receipt');
  return result(await send('post', '/v1/family/invoices/{invoice}/proofs', { params: { invoice: id }, form: sent, idempotencyKey: key }));
}

export type { Rating };

/**
 * Makes the invoice for extra lessons on a course this person pays for; it
 * is then paid like any other. One key per press, so a retry makes one.
 */
export async function buyExtraLessons(enrollment: string, learnerId: string, lessons: number, key: string): Promise<Result & { number?: string; total?: string }> {
  const made = await send<{ number: string; total: string }>('post', '/v1/family/enrollments/{enrollment}/extra-lessons', {
    params: { enrollment },
    body: { lessons, learner_id: learnerId },
    idempotencyKey: key,
  });
  if (!made.ok) return result(made);
  refresh();
  return { ok: true, number: made.data.number, total: made.data.total };
}

/** A guardian agrees to a child's account, to the wording (version) they read. */
export async function grantConsent(student: string, version: number, agree: boolean): Promise<Result> {
  return result(await send('post', '/v1/family/consents/{student}', { params: { student }, body: { agree, version } }));
}

/** A guardian takes their consent back: the child is signed out everywhere. */
export async function withdrawConsent(student: string): Promise<Result> {
  return result(await send('post', '/v1/family/consents/{student}/withdraw', { params: { student } }));
}

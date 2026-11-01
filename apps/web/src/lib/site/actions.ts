'use server';

import { refresh } from 'next/cache';
import { read, send } from '@/lib/api/server';
import type { LocalTime } from '@/lib/time';
import { birthYearFor, normalizePhone, stillOffered, type TrialDraft } from './trial';

/**
 * A form's answer for the page: sent, or what to put right (by field), or a
 * word for the whole form; with what was typed, so the form shows it again
 * (React empties a form once its action is done).
 */
export type SiteFormState = {
  status: 'idle' | 'sent' | 'failed';
  errors: Record<string, string>;
  message?: string;
  /** Too many requests from here (the API's throttle): the form says so in its own words. */
  limited?: boolean;
  values?: Record<string, string | string[]>;
};

/** What a visitor typed, to put back (never the form's token or its hidden field). */
function typed(form: FormData): Record<string, string | string[]> {
  const values: Record<string, string | string[]> = {};
  for (const key of new Set(form.keys())) {
    if (key === 'form_token' || key === 'website' || key.startsWith('$')) continue;
    const all = form.getAll(key).map(String);
    values[key] = all.length > 1 || key.endsWith('_ids') ? all : (all[0] ?? '');
  }
  return values;
}

const FIELDS = ['academy_name', 'contact_name', 'email', 'phone', 'country', 'students', 'plan', 'message', 'utm_source', 'utm_medium', 'utm_campaign', 'form_token', 'website'] as const;

/** An academy asks to join the platform (the platform's page). */
export async function askToJoinPlatform(_previous: SiteFormState, form: FormData): Promise<SiteFormState> {
  const body: Record<string, string> = {};
  for (const field of FIELDS) body[field] = String(form.get(field) ?? '');
  const done = await send('post', '/v1/site/platform/requests', { body });
  if (done.ok) return { status: 'sent', errors: {} };
  const errors = Object.fromEntries(Object.entries(done.problem.errors).map(([field, messages]) => [field, messages[0] ?? '']));
  if (done.problem.status === 429) return { status: 'failed', errors, limited: true, values: typed(form) };
  return { status: 'failed', errors, message: done.problem.status === 422 ? undefined : done.problem.title || undefined, values: typed(form) };
}

const ACADEMY_FIELDS = ['kind', 'name', 'phone', 'email', 'country', 'timezone', 'subject_id', 'child_name', 'child_birth_year', 'years_teaching', 'message', 'utm_source', 'utm_medium', 'utm_campaign', 'form_token', 'website'] as const;

/** A family asks the academy for a free trial lesson, or a teacher asks to teach there (the academy's page). */
export async function askTheAcademy(_previous: SiteFormState, form: FormData): Promise<SiteFormState> {
  const body: Record<string, unknown> = {};
  for (const field of ACADEMY_FIELDS) {
    const value = String(form.get(field) ?? '').trim();
    if (value !== '' || field === 'website' || field === 'form_token') body[field] = value;
  }
  if (body.kind === 'trial') body.for_child = form.get('for_child') === 'on';
  if (body.kind === 'teach') body.subject_ids = form.getAll('subject_ids').map(String);
  const done = await send('post', '/v1/site/requests', { body });
  if (done.ok) return { status: 'sent', errors: {} };
  const errors = Object.fromEntries(Object.entries(done.problem.errors).map(([field, messages]) => [field, messages[0] ?? '']));
  if (done.problem.status === 429) return { status: 'failed', errors, limited: true, values: typed(form) };
  return { status: 'failed', errors, message: done.problem.status === 422 ? undefined : done.problem.title || undefined, values: typed(form) };
}

/* ── A family's own free trial (docs/architecture/02, section 27) ─────────── */


/** What the API said no to, in its words: by field, and for the whole. */
export type Refusal = { status: number; message: string; errors: Record<string, string>; retryAfter?: number };
export type Answer<T> = { ok: true; data: T } | { ok: false; refusal: Refusal };

function refusalOf(problem: { status: number; title: string; errors: Record<string, string[]>; extra?: Record<string, unknown> }): Refusal {
  const errors = Object.fromEntries(Object.entries(problem.errors).map(([field, messages]) => [field, messages[0] ?? '']));
  const first = Object.values(errors).find(Boolean);
  const retry = Number((problem.extra as { retry_after?: unknown } | undefined)?.retry_after);
  return {
    status: problem.status,
    // A 422's words are its fields'; anything else says it in its title (a problem the page words itself when empty).
    message: (problem.status === 422 ? first : problem.title) ?? '',
    errors,
    retryAfter: Number.isFinite(retry) && retry > 0 ? retry : undefined,
  };
}

function answered<T>(done: { ok: true; data: unknown } | { ok: false; problem: Parameters<typeof refusalOf>[0] }): Answer<T> {
  return done.ok ? { ok: true, data: (done.data as { data: T }).data } : { ok: false, refusal: refusalOf(done.problem) };
}

/** A search for times: the subject, the learner's age (a child's year of birth), the teacher they would rather have. */
export type TimesQuery = { subjectId: string; childAge?: string; birthYear?: number | null; teacherGender?: string; from?: string | null; timezone?: string };

/** The times a family may book a free trial at, on its clock (GET site/trial-slots), soonest first. */
export async function trialTimes(query: TimesQuery): Promise<Answer<LocalTime[]>> {
  const birthYear = query.birthYear ?? (query.childAge ? birthYearFor(query.childAge, new Date().getUTCFullYear()) : null);
  const done = await read<{ data: { starts_at: LocalTime }[] }>('/v1/site/trial-slots', {
    query: {
      subject_id: query.subjectId,
      birth_year: birthYear ?? undefined,
      teacher_gender: query.teacherGender || undefined,
      from: query.from ?? undefined,
      timezone: query.timezone || undefined,
    },
  });
  if (!done.ok) return { ok: false, refusal: refusalOf(done.problem) };
  return { ok: true, data: done.data.data.map((s) => s.starts_at).filter((t): t is LocalTime => Boolean(t)) };
}

export type CodeSent = { challenge_id: string; channel: string; sent_to: string; expires_at: LocalTime | null; resend_after: number };

/** A code to the family's number before booking (POST site/trials/code): the same answer whether or not the number is known. */
export async function sendTrialCode(phone: string, channel?: 'whatsapp' | 'sms'): Promise<Answer<CodeSent>> {
  return answered<CodeSent>(await send('post', '/v1/site/trials/code', { body: { phone: normalizePhone(phone), ...(channel ? { channel } : {}) } }));
}

export type BookedTrial = { starts_at: LocalTime | null; subject: string; teacher?: string; minutes?: number };

/**
 * Why a booking did not go through, for the page to say and do the right thing:
 * `taken` (the time went; pick another), `code` (the code is wrong, ended or
 * locked), `fields` (something typed is not right), `refused` (the academy
 * books this one itself: the API's sentence says what to do), `limited`,
 * `failed`. `codeSpent`: the code was used up, so another is needed.
 */
export type BookRefusal = { kind: 'taken' | 'code' | 'fields' | 'refused' | 'limited' | 'failed'; message: string; errors: Record<string, string>; codeSpent: boolean };

/** The request as the page's form sends it, from what the family typed. */
function requestBody(draft: TrialDraft, timezone: string, utm: Record<string, string | undefined>): Record<string, unknown> {
  const child = draft.who === 'child';
  const body: Record<string, unknown> = {
    name: draft.name.trim(),
    phone: normalizePhone(draft.phone),
    email: draft.email.trim() || undefined,
    country: draft.country || undefined,
    timezone: timezone || undefined,
    help_choose: draft.helpChoose,
    subject_id: draft.helpChoose ? undefined : draft.subjectId || undefined,
    for_child: child,
    child_name: child ? draft.childName.trim() : undefined,
    child_birth_year: child && draft.childAge ? birthYearFor(draft.childAge, new Date().getUTCFullYear()) ?? undefined : undefined,
    preferred_days: draft.days,
    preferred_from: draft.from === '' ? undefined : Number(draft.from),
    preferred_to: draft.to === '' ? undefined : Number(draft.to),
    teacher_gender: draft.teacherGender || undefined,
    message: draft.message.trim() || undefined,
    utm_source: utm.source || undefined,
    utm_medium: utm.medium || undefined,
    utm_campaign: utm.campaign || undefined,
  };
  return Object.fromEntries(Object.entries(body).filter(([, v]) => v !== undefined));
}

/** Whether a time is still on the list (the same search the booking uses), so a code is not spent on a time already gone. */
async function stillThere(subjectId: string, draft: TrialDraft, time: LocalTime, timezone: string): Promise<boolean | null> {
  const times = await trialTimes({
    subjectId,
    childAge: draft.who === 'child' ? draft.childAge : undefined,
    teacherGender: draft.teacherGender,
    from: time.local.slice(0, 10),
    timezone,
  });
  return times.ok ? stillOffered(times.data, time.utc) : null;
}

/**
 * Books the free trial with the code (POST site/trials): first a look that the
 * time is still free (else `taken` with the code kept), then the booking;
 * a time refused after the code was checked is told apart by looking again.
 */
export async function bookTrial(input: {
  draft: TrialDraft;
  subjectId: string;
  time: LocalTime;
  challengeId: string;
  code: string;
  timezone: string;
  utm: Record<string, string | undefined>;
  key: string;
}): Promise<{ ok: true; data: BookedTrial } | { ok: false; refusal: BookRefusal }> {
  const { draft, subjectId, time, timezone } = input;
  if ((await stillThere(subjectId, draft, time, timezone)) === false) {
    return { ok: false, refusal: { kind: 'taken', message: '', errors: {}, codeSpent: false } };
  }
  const done = await send<{ data: BookedTrial }>('post', '/v1/site/trials', {
    body: { ...requestBody(draft, timezone, input.utm), challenge_id: input.challengeId, code: input.code, starts_at: time.utc },
    idempotencyKey: input.key,
  });
  if (done.ok) return { ok: true, data: done.data.data };
  const refusal = refusalOf(done.problem);
  if (done.problem.status === 429) return { ok: false, refusal: { kind: 'limited', message: refusal.message, errors: {}, codeSpent: false } };
  if (done.problem.status !== 422) return { ok: false, refusal: { kind: 'failed', message: '', errors: {}, codeSpent: false } };
  const { errors } = refusal;
  if (errors.code || errors.challenge_id) {
    return { ok: false, refusal: { kind: 'code', message: errors.code ?? errors.challenge_id ?? '', errors, codeSpent: false } };
  }
  if (errors.starts_at) {
    const there = await stillThere(subjectId, draft, time, timezone);
    return { ok: false, refusal: { kind: there === false ? 'taken' : 'refused', message: errors.starts_at, errors, codeSpent: true } };
  }
  return { ok: false, refusal: { kind: Object.keys(errors).length ? 'fields' : 'failed', message: refusal.message, errors, codeSpent: true } };
}

/** A free trial asked for without a time (POST site/requests): the academy answers within its hours. */
export async function askForTrial(input: { draft: TrialDraft; token: string; timezone: string; utm: Record<string, string | undefined> }): Promise<Answer<null>> {
  const done = await send('post', '/v1/site/requests', {
    body: { kind: 'trial', ...requestBody(input.draft, input.timezone, input.utm), form_token: input.token, website: '' },
  });
  return done.ok ? { ok: true, data: null } : { ok: false, refusal: refusalOf(done.problem) };
}

/** Books the time picked from the "pick a time" link (POST site/trials/pick/{token}): no code, the link went to that number. */
export async function bookFromPick(token: string, utc: string, timezone: string, key: string): Promise<Answer<BookedTrial>> {
  return answered<BookedTrial>(await send('post', '/v1/site/trials/pick/{token}', {
    params: { token },
    body: { starts_at: utc, ...(timezone ? { timezone } : {}) },
    idempotencyKey: key,
  }));
}

/** A coming free trial from its own link (GET site/trials/lesson/{token}). */
export type TrialLesson = {
  subject: string;
  teacher: string;
  starts_at: LocalTime | null;
  minutes: number;
  can_move: boolean;
  cannot_move: string | null;
  moves: number;
  /** Times it may still be moved. */
  moves_left: number;
  move_until: LocalTime | null;
};

export async function trialLesson(token: string, timezone: string): Promise<Answer<TrialLesson>> {
  const done = await read<{ data: TrialLesson }>('/v1/site/trials/lesson/{token}', { params: { token }, query: { timezone: timezone || undefined } });
  return done.ok ? { ok: true, data: done.data.data } : { ok: false, refusal: refusalOf(done.problem) };
}

/** The other times the trial's teacher is free (GET site/trials/lesson/{token}/slots). */
export async function lessonTimes(token: string, from: string | null, timezone: string): Promise<Answer<LocalTime[]>> {
  const done = await read<{ data: { starts_at: LocalTime }[] }>('/v1/site/trials/lesson/{token}/slots', {
    params: { token },
    query: { from: from ?? undefined, timezone: timezone || undefined },
  });
  if (!done.ok) return { ok: false, refusal: refusalOf(done.problem) };
  return { ok: true, data: done.data.data.map((s) => s.starts_at).filter((t): t is LocalTime => Boolean(t)) };
}

/** Moves the trial (POST site/trials/lesson/{token}/move). */
export async function moveTrial(token: string, utc: string, timezone: string, key: string): Promise<Answer<TrialLesson>> {
  return answered<TrialLesson>(await send('post', '/v1/site/trials/lesson/{token}/move', {
    params: { token },
    body: { starts_at: utc, ...(timezone ? { timezone } : {}) },
    idempotencyKey: key,
  }));
}

/** "We cannot come" (POST site/trials/lesson/{token}/cancel), with why if they said. */
export async function cancelTrial(token: string, note: string, key: string): Promise<Answer<{ cancelled: boolean }>> {
  return answered<{ cancelled: boolean }>(await send('post', '/v1/site/trials/lesson/{token}/cancel', {
    params: { token },
    body: note.trim() ? { note: note.trim().slice(0, 500) } : {},
    idempotencyKey: key,
  }));
}

/* ── After the free trial (docs/architecture/02, section 27, J2) ─────────── */

/**
 * Where one more free trial is booked from: the report's link (the second
 * trial with another teacher), the "book again" link (after a trial that did
 * not take place), or a signed-in family's own trial (either, by its lesson).
 */
export type NextTrialSource = { from: 'report' | 'rebook'; token: string } | { from: 'family'; session: string };

/** The times one more free trial may be booked at, on the family's clock, soonest first. */
export async function nextTrialTimes(source: NextTrialSource, from: string | null, timezone: string): Promise<Answer<LocalTime[]>> {
  const query = { from: from ?? undefined, timezone: timezone || undefined };
  const done = source.from === 'family'
    ? await read<{ data: { starts_at: LocalTime }[] }>('/v1/family/trials/{session}/slots', { params: { session: source.session }, query })
    : source.from === 'report'
      ? await read<{ data: { starts_at: LocalTime }[] }>('/v1/site/trials/report/{token}/slots', { params: { token: source.token }, query })
      : await read<{ data: { starts_at: LocalTime }[] }>('/v1/site/trials/rebook/{token}/slots', { params: { token: source.token }, query });
  if (!done.ok) return { ok: false, refusal: refusalOf(done.problem) };
  return { ok: true, data: done.data.data.map((s) => s.starts_at).filter((t): t is LocalTime => Boolean(t)) };
}

/** Books one more free trial at a time from the list (no code: the link went to the family's number, or they are signed in). */
export async function bookNextTrial(source: NextTrialSource, utc: string, timezone: string, key: string): Promise<Answer<BookedTrial>> {
  const body = { starts_at: utc, ...(timezone ? { timezone } : {}) };
  if (source.from === 'family') {
    const done = answered<BookedTrial>(await send('post', '/v1/family/trials/{session}/book', { params: { session: source.session }, body: { starts_at: utc }, idempotencyKey: key }));
    if (done.ok) refresh();
    return done;
  }
  return answered<BookedTrial>(source.from === 'report'
    ? await send('post', '/v1/site/trials/report/{token}/book', { params: { token: source.token }, body, idempotencyKey: key })
    : await send('post', '/v1/site/trials/rebook/{token}/book', { params: { token: source.token }, body, idempotencyKey: key }));
}

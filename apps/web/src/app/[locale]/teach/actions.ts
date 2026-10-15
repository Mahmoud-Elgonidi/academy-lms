'use server';

import { refresh } from 'next/cache';
import { send, type Outcome } from '@/lib/api/server';
import type { ShiftBlock } from '@/lib/teach/types';
import type { VoiceDraft } from '@/lib/teach/voice-draft';

/**
 * The teacher's writes. Each calls the same API endpoint (and so the same
 * Action) as the app and the panels, then refreshes the page it came from.
 * A form gets back the API's own words, and the fields it named.
 */
export type Result = { ok: boolean; message?: string; errors?: Record<string, string[]> };

function result(outcome: Outcome<unknown>): Result {
  if (outcome.ok) {
    refresh();
    return { ok: true };
  }
  return { ok: false, message: outcome.problem.title || undefined, errors: outcome.problem.errors };
}

export type ReportPayload = {
  engagement?: number | null;
  overall?: number | null;
  notes_for_student?: string | null;
  notes_internal?: string | null;
  absence_note?: string | null;
  items?: { node_id: string; outcome: string; score: number | null; note: string | null }[];
  homework?: { title: string; instructions: string | null; accepts: string; due_days: number; expected?: { surah: number; from: number; to: number } | null } | null;
  /** Answers to the subject's questions, by key. */
  answers?: Record<string, number | string | boolean>;
};

/** Sends (or corrects) a lesson report or absence note. */
export async function submitReport(id: string, payload: ReportPayload): Promise<Result> {
  return result(await send('put', '/v1/teacher/reports/{report}', { params: { report: id }, body: payload }));
}

export type DraftResult = { ok: true; draft: VoiceDraft } | { ok: false; message?: string };

/**
 * A draft of the open report from the teacher's voice note (`audio`) and the
 * form as it stands (`current`, JSON as PUT takes it). Nothing is saved, so
 * nothing refreshes: the form takes the draft in and the teacher sends it.
 */
export async function draftReport(id: string, form: FormData): Promise<DraftResult> {
  const audio = form.get('audio');
  if (!(audio instanceof File) || audio.size === 0) return { ok: false };
  const sent = new FormData();
  sent.set('audio', audio, audio.name || 'note.webm');
  const current = form.get('current');
  if (typeof current === 'string') sent.set('current', current);
  const outcome = await send<{ data: VoiceDraft }>('post', '/v1/teacher/reports/{report}/draft', { params: { report: id }, form: sent });
  return outcome.ok ? { ok: true, draft: outcome.data.data } : { ok: false, message: outcome.problem.title || undefined };
}

/** Marks handed-in homework: a grade from 1 to 5 (or none) and words for the student. */
export async function markHomework(id: string, grade: number | null, feedback: string): Promise<Result> {
  return result(
    await send('post', '/v1/teacher/homework/{homework}/review', {
      params: { homework: id },
      body: { grade, feedback: feedback.trim() || null },
    }),
  );
}

/** The level, plan and notes after a trial lesson (again to correct them). */
export async function assessTrial(id: string, input: { level: string; plan_id: string | null; per_week: number | null; notes: string }): Promise<Result> {
  return result(await send('put', '/v1/teacher/trials/{trial}/assessment', { params: { trial: id }, body: input }));
}

export type ShiftsPayload = {
  blocks: ShiftBlock[];
  effective_from: string;
  note: string | null;
  min_weekly_hours: number | null;
  max_weekly_hours: number | null;
  submit: boolean;
};

/** Saves the proposal: a draft, or sent to the academy. */
export async function saveShifts(payload: ShiftsPayload): Promise<Result> {
  return result(await send('put', '/v1/teacher/shifts/proposal', { body: payload }));
}

export async function withdrawShifts(): Promise<Result> {
  return result(await send('post', '/v1/teacher/shifts/proposal/withdraw'));
}

/** Takes the academy's counter-offer as it is. */
export async function acceptShifts(): Promise<Result> {
  return result(await send('post', '/v1/teacher/shifts/proposal/accept'));
}

export async function commentOnShifts(body: string): Promise<Result> {
  return result(await send('post', '/v1/teacher/shifts/proposal/comments', { body: { body } }));
}

export type TimeOffPayload = {
  first_day: string;
  last_day?: string;
  from?: string;
  to?: string;
  reason: string;
  note?: string;
};

/** Asks for time off; a retry of the same form sends the same key, so it counts once. */
export async function requestTimeOff(payload: TimeOffPayload, key: string): Promise<Result> {
  return result(await send('post', '/v1/teacher/time-off', { body: payload, idempotencyKey: key }));
}

export async function withdrawTimeOff(id: string): Promise<Result> {
  return result(await send('delete', '/v1/teacher/time-off/{timeOff}', { params: { timeOff: id } }));
}

/** Sends a vetting document (a criminal record check, an ID, a qualification) for the academy to check; one key per submit. */
export async function sendDocument(form: FormData, key: string): Promise<Result> {
  const sent = new FormData();
  for (const name of ['kind', 'reference', 'issued_on', 'expires_on']) {
    const value = String(form.get(name) ?? '').trim();
    if (value) sent.set(name, value);
  }
  const file = form.get('file');
  if (file instanceof File && file.size > 0) sent.set('file', file, file.name || 'document');
  return result(await send('post', '/v1/teacher/checks', { form: sent, idempotencyKey: key }));
}

/** Saves the teacher's own Zoom or Meet link (empty removes it); families join outside-link lessons through it. */
export async function saveMeetingLink(url: string): Promise<Result> {
  return result(await send('put', '/v1/teacher/meeting-link', { body: { url: url.trim() } }));
}

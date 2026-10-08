'use server';

import { refresh } from 'next/cache';
import { send, type Outcome } from '@/lib/api/server';
import type { ShiftBlock } from '@/lib/teach/types';

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
  homework?: { title: string; instructions: string | null; accepts: string; due_days: number } | null;
  /** Answers to the subject's questions, by key. */
  answers?: Record<string, number | string | boolean>;
};

/** Sends (or corrects) a lesson report or absence note. */
export async function submitReport(id: string, payload: ReportPayload): Promise<Result> {
  return result(await send('put', '/v1/teacher/reports/{report}', { params: { report: id }, body: payload }));
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

'use server';

import { refresh } from 'next/cache';
import { read, send } from '@/lib/api/server';
import type { Consents, ChildConsent } from '@/lib/family/types';
import { startProblemOf, type StartProblem } from '@/lib/start/flow';
import type { StartedCourse } from '@/lib/start/types';

/**
 * The start flow's writes (docs/architecture/02, section 27, J3), through the
 * same endpoints as the app: starting the course (one key per confirmation:
 * a retry of the same press starts it once; a refused one is answered again
 * the same way, so the page sends a new key after a refusal), the guardian's
 * consent when the API asks for it first, and calling a start off before
 * paying. The API checks every rule again.
 */

export type StartBody = {
  learner_id: string;
  subject_id: string;
  plan_id: string;
  price_minor: number;
  currency: string;
  teacher_id?: string;
  times?: string[];
  group_id?: string;
};

export type StartAnswer = { ok: true; course: StartedCourse } | { ok: false; problem: StartProblem };

export async function startCourse(body: StartBody, key: string): Promise<StartAnswer> {
  const done = await send<{ data: StartedCourse }>('post', '/v1/family/courses', { body, idempotencyKey: key });
  if (!done.ok) return { ok: false, problem: startProblemOf(done.problem) };
  return { ok: true, course: done.data.data };
}

/** The consent wording for one child, as the family's privacy page shows it (GET family/consents). */
export async function consentFor(student: string): Promise<ChildConsent | null> {
  const found = await read<Consents>('/v1/family/consents');
  if (!found.ok) return null;
  return found.data.children.find((child) => child.student.id === student) ?? null;
}

/** The guardian agrees to the child's account, to the wording (version) they read. */
export async function agreeFor(student: string, version: number): Promise<{ ok: boolean; message?: string }> {
  const done = await send('post', '/v1/family/consents/{student}', { params: { student }, body: { agree: true, version } });
  if (done.ok) return { ok: true };
  return { ok: false, message: Object.values(done.problem.errors)[0]?.[0] ?? (done.problem.title || undefined) };
}

/** Calls off a course this payer started, before paying (one key per confirmation). */
export async function cancelStart(enrollment: string, key: string): Promise<{ ok: true; message: string } | { ok: false; message?: string }> {
  const done = await send<{ data: { message: string } }>('post', '/v1/family/courses/{enrollment}/cancel', { params: { enrollment }, idempotencyKey: key });
  if (!done.ok) {
    const problem = startProblemOf(done.problem);
    return { ok: false, message: problem.detail || undefined };
  }
  refresh();
  return { ok: true, message: done.data.data.message };
}

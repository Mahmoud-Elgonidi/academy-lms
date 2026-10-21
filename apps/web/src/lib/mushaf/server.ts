import 'server-only';
import { read } from '../api/server';
import type { MushafPage, Notebook } from './types';

/**
 * The shared mushaf and the mistakes notebook, read for a page as an extra:
 * a lesson of another subject, or a learner without them, leaves the page as
 * it is (null), never a "not found" of its own.
 */

/** A lesson's shared mushaf at the page the teacher has open (null: the lesson has none for me). */
export async function lessonMushaf(session: string): Promise<MushafPage | null> {
  const done = await read<{ data: MushafPage }>('/v1/learning/lessons/{session}/mushaf', { params: { session } });
  return done.ok ? done.data.data : null;
}

/** The learner's mistakes notebook (a guardian names whose). */
export async function familyNotebook(learnerId: string): Promise<Notebook | null> {
  const done = await read<{ data: Notebook }>('/v1/learning/mistakes', { query: { learner_id: learnerId } });
  return done.ok ? done.data.data : null;
}

/** A student's mistakes notebook, for their teacher. */
export async function teacherNotebook(enrollment: string): Promise<Notebook | null> {
  const done = await read<{ data: Notebook }>('/v1/teacher/students/{enrollment}/mistakes', { params: { enrollment } });
  return done.ok ? done.data.data : null;
}

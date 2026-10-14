import { dayLabel } from '../format';
import type { LocalTime } from '../time';
import type { Stage } from './types';

/**
 * A course's status and days as the API gives them on the family's course
 * endpoints (the balance, the journey): paused until a day, a pause ahead,
 * ending from a day, or ended. The days are the academy's calendar days
 * (docs/architecture/02, section 3, "إيقاف الكورس وإنهاؤه"), written as they
 * are, never converted.
 */
export type CourseDays = {
  status?: string | null;
  paused_from?: LocalTime | null;
  paused_until?: LocalTime | null;
  ends_on?: LocalTime | null;
};

/** Which words to say (family.course.*) and their values; null when the course simply goes on. */
export type CourseState = {
  key: 'paused' | 'pausedKids' | 'pauseAhead' | 'ending' | 'ended';
  tone: 'info' | 'attention' | 'muted';
  values: Record<string, string>;
} | null;

/** How a course stands, in the words of the viewer's look: a child in the kids' look reads of a break, not a pause. */
export function courseState(course: CourseDays, locale: string, stage: Stage = 'classic'): CourseState {
  const day = (t: LocalTime | null | undefined) => (t ? dayLabel(t.local, locale) : '');
  if (course.status === 'ended') return { key: 'ended', tone: 'muted', values: {} };
  if (course.ends_on) return { key: 'ending', tone: 'attention', values: { day: day(course.ends_on) } };
  if (course.status === 'paused' && course.paused_until) {
    return { key: stage === 'kids' ? 'pausedKids' : 'paused', tone: 'info', values: { day: day(course.paused_until) } };
  }
  if (course.paused_from && course.paused_until) return { key: 'pauseAhead', tone: 'info', values: { from: day(course.paused_from), until: day(course.paused_until) } };
  return null;
}

/** Whether lessons can be booked or bought on it now: only while it is simply on. */
export function courseIsOn(course: CourseDays): boolean {
  return (course.status ?? 'active') === 'active';
}

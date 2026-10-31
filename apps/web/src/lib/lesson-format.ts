/**
 * Private or group (docs/architecture/02, section 25): how a lesson, a course
 * or a plan says which it is, worked out from what the API answers, so every
 * page draws the same badge (components/lesson-format.tsx). A group lesson
 * carries its seats and the learner's own ("seat 4 of 5"); a family reads its
 * classmates' first names (the kids' look only how many), a teacher everyone
 * seated. A course says it itself (the journey's courses, the balance), never
 * guessed from its lessons.
 */

export type LessonFormat = 'private' | 'group';

/** `group` as the API writes it on a lesson (LessonPresenter): numbers may come as strings, lists as one name. */
export type RawLessonGroup = {
  capacity: number | string | null;
  seats_taken: number | string | null;
  /** The family: the learner's seat by when they joined ("seat 4 of 5": 4); null for the teacher. */
  seat_number?: number | string | null;
  classmates?: string | string[] | null;
  classmates_count?: number | string | null;
  classmates_label?: string | null;
  seat?: string | null;
  seat_label?: string | null;
  /** The teacher: every student seated. */
  students?: { id: string; name: string }[] | null;
};

/** A group lesson's seats and people, as the pages use them. */
export type LessonGroup = {
  capacity: number;
  taken: number;
  /** The family: the learner's own seat number ("seat 4 of 5"), null for a teacher. */
  seatNumber: number | null;
  /** The family: classmates' first names (null in the kids' look). */
  classmates: string[] | null;
  /** The family: how many others (the kids' look reads only this). */
  classmatesCount: number | null;
  /** The API's own words for it ("مع زميلين"). */
  classmatesLabel: string | null;
  /** The family: what the learner's own seat came to, once decided. */
  seat: string | null;
  seatLabel: string | null;
  /** The teacher: every student seated. */
  students: { id: string; name: string }[];
};

const count = (value: unknown): number | null => {
  const n = typeof value === 'number' ? value : typeof value === 'string' && value.trim() !== '' ? Number(value) : NaN;
  return Number.isFinite(n) && n >= 0 ? Math.floor(n) : null;
};

const names = (value: unknown): string[] =>
  (Array.isArray(value) ? value : typeof value === 'string' && value.trim() !== '' ? [value] : []).filter((v): v is string => typeof v === 'string' && v.trim() !== '');

const people = (value: unknown): { id: string; name: string }[] =>
  (Array.isArray(value) ? value : []).flatMap((p: unknown) =>
    p && typeof p === 'object' && typeof (p as { id?: unknown }).id === 'string' ? [{ id: (p as { id: string }).id, name: String((p as { name?: unknown }).name ?? '') }] : [],
  );

/** A lesson's format: group when the API says so (or sends a group), else private. */
export function formatOf(lesson: { format?: string | null; group?: RawLessonGroup | null }): LessonFormat {
  return lesson.format === 'group' || (lesson.format == null && lesson.group != null) ? 'group' : 'private';
}

/** A group lesson's seats and people, or null for a private one. */
export function groupOf(lesson: { format?: string | null; group?: RawLessonGroup | null }): LessonGroup | null {
  const raw = lesson.group;
  if (formatOf(lesson) !== 'group' || !raw) return null;
  const taken = count(raw.seats_taken) ?? 0;
  const capacity = Math.max(count(raw.capacity) ?? taken, taken);
  const classmates = raw.classmates == null ? null : names(raw.classmates);
  return {
    capacity,
    taken,
    seatNumber: count(raw.seat_number) || null,
    classmates,
    classmatesCount: count(raw.classmates_count) ?? (classmates ? classmates.length : null),
    classmatesLabel: raw.classmates_label ?? null,
    seat: raw.seat ?? null,
    seatLabel: raw.seat_label ?? null,
    students: people(raw.students),
  };
}

/** Seats as dots past which a row would only be noise: more than this many and the badge shows the numbers alone. */
export const MAX_DOTS = 12;

/**
 * The seat dots (●●●○○): one per seat, taken first in the reading order,
 * or none when the group is larger than a row of dots reads (MAX_DOTS).
 */
export function seatDots(taken: number, capacity: number, max = MAX_DOTS): boolean[] {
  const seats = Math.max(0, Math.floor(capacity));
  if (seats === 0 || seats > max) return [];
  const full = Math.min(Math.max(0, Math.floor(taken)), seats);
  return Array.from({ length: seats }, (_, i) => i < full);
}

/** Seats left to take (never below none). */
export function seatsLeft(group: Pick<LessonGroup, 'taken' | 'capacity'>): number {
  return Math.max(0, group.capacity - group.taken);
}

/** The first `shown` names and how many more there are ("ليلى، عمر +2"). */
export function someNames(all: readonly string[], shown = 3): { names: string[]; more: number } {
  return { names: all.slice(0, shown), more: Math.max(0, all.length - shown) };
}

/** A first name: what a family reads of another family's child, and what a roster chip fits. */
export function firstName(name: string): string {
  return name.trim().split(/\s+/)[0] ?? name;
}

/** A course's group as the journey and the balance write it: its seats and the learner's own; null for a private course. */
export type CourseGroupRaw = { id: string; capacity: number; seats_taken: number; seat: number; status?: string };

/** A course's group, as the course and money cards draw it. */
export type CourseGroup = { id: string; capacity: number; taken: number; seat: number | null };

/** A course's group from what the API says of the course itself (format and group), or null for a private one. */
export function courseGroup(course: { format?: string | null; group?: CourseGroupRaw | null }): CourseGroup | null {
  const raw = course.group;
  if (course.format === 'private' || !raw) return null;
  const taken = count(raw.seats_taken) ?? 0;
  return { id: raw.id, capacity: Math.max(count(raw.capacity) ?? taken, taken), taken, seat: count(raw.seat) || null };
}

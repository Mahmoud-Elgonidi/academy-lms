import type { LocalTime } from '../time';
import type { RecitationCheck } from '../teach/recitation';

/**
 * What the hifz endpoints answer (the Quran memorization map and its daily
 * review), as the pages read them: GET learning/hifz and its pages for the
 * learner (a guardian passes `learner_id`), GET teacher/students/{enrollment}/hifz
 * for their teacher.
 */

/** Where a page stands: new, learning, or memorized and strong, fading or weak now. */
export type Strength = 'new' | 'learning' | 'strong' | 'fading' | 'weak';

/** 1 forgot, 2 hard, 3 good, 4 easy. */
export type Grade = 1 | 2 | 3 | 4;

/** How well a teacher says the student knows what they mark. */
export type Mark = 'strong' | 'good' | 'weak';

/** Memorized and learning pages; of the memorized, strong, fading and weak now; due by the end of the learner's day. */
export type HifzCounts = { memorized: number; learning: number; strong: number; fading: number; weak: number; due: number };

export type Juz = { juz: number; pages: { page: number; strength: Strength }[] };

/** The map itself: every page of the mushaf by part (pages_total 0 while the platform has no Quran text: nothing to show). */
export type HifzMapData = {
  pages_total: number;
  counts: HifzCounts;
  juzs: Juz[];
  /** Each strength's name in the reader's language. */
  strengths: Partial<Record<Strength, string>>;
};

export type SurahRef = { number: number; name_ar: string; name_en: string };

/** A page of today's review: its title as the API writes it ("صفحة ٥٨٢ · النبأ"), most overdue first. */
export type WirdPage = { page: number; juz: number; title: string; surahs: SurahRef[]; strength: Strength; overdue_days: number };

export type Wird = {
  /** Pages a day at most (the academy's). */
  daily: number;
  done_today: number;
  /** What is left of today's review. */
  pages: WirdPage[];
};

/** A page recited for the review, heard and checked word by word in the background. */
export type Recitation = Pick<RecitationCheck, 'status' | 'status_label' | 'failure' | 'summary_label' | 'verses' | 'extra' | 'note'> & {
  id: string;
  page: number;
  /** The review it counted as. */
  grade: Grade | null;
  grade_label: string | null;
  recited_at: LocalTime | null;
};

/** GET learning/hifz. mode check: a page is recited and checked; self: the learner says how it went. */
export type Hifz = HifzMapData & {
  learner_id: string;
  mode: 'check' | 'self';
  wird: Wird;
  /** The latest page recited in the last day, with its check. */
  latest_recitation: Recitation | null;
};

/** GET learning/hifz/pages/{page}: its verses as Tanzil writes them, and where it stands. */
export type HifzPage = {
  page: number;
  juz: number;
  title: string;
  surahs: SurahRef[];
  verses: { surah: number; verse: number; text: string }[];
  attribution: string;
  strength: Strength;
  due_at: LocalTime | null;
};

/** A review on the teacher's list: from the teacher, a report, a homework's check, the daily review, or the learner's own grade. */
export type HifzReview = { page: number; grade: Grade; grade_label: string; source: string; source_label: string; at: LocalTime | null };

/** GET teacher/students/{enrollment}/hifz. */
export type TeacherHifz = HifzMapData & {
  enrollment_id: string;
  /** Whether the map belongs to this course: its curriculum reads the Quran, or the student already has pages. */
  follows: boolean;
  student: { id: string; name: string };
  /** The last two weeks' reviews, newest first. */
  recent: HifzReview[];
  /** What a teacher may mark, a surah at a time, with its pages. */
  surahs: (SurahRef & { verses: number; pages: number[] })[];
  /** Each way of knowing it, in the reader's language. */
  marks: Partial<Record<Mark, string>>;
};

/** The journey's line about today's review (null until the learner has pages on the map). */
export type JourneyHifz = { due: number; done_today: number; daily: number };

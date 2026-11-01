import type { Tone } from '@/components/ui';
import type { Juz } from '../hifz/types';
import type { Certificate, ExamStatus, GradeKey, MistakeType, PartKey, PartRecord, Portion } from './types';

/**
 * The Quran program's plain logic, for the family's Quran page, the
 * teacher's lesson panel, the student's plan, the examiner's form and the
 * public verification: whether the plan leaves the Quran out, the thirty
 * parts as a bar, the kids' stars, a grade's and an exam's tone, and the
 * record a form sends.
 */

export const PARTS: readonly PartKey[] = ['new', 'near', 'far'];

export const GRADE_ORDER: readonly GradeKey[] = ['excellent', 'very_good', 'good', 'acceptable', 'repeat'];

/** The parts of the mushaf. */
export const JUZ_COUNT = 30;

/** The API's problem when the academy's plan leaves the Quran out. */
export const FEATURE_OFF = 'feature-not-in-plan';

/** Whether a refused read means the academy's plan has no Quran (a calm "not available", never an error page). */
export function featureOff(problem: { status: number; type?: string } | null | undefined): boolean {
  return problem?.status === 403 && problem.type === FEATURE_OFF;
}

/** Whether the academy's plan includes the Quran (`me.plan_features`). */
export function quranInPlan(features: readonly string[] | null | undefined): boolean {
  return (features ?? []).includes('quran');
}

/** A share (0 to 1) as a whole percent, held between 0 and 100. */
export function percentOf(progress: number | null | undefined): number {
  if (typeof progress !== 'number' || Number.isNaN(progress)) return 0;
  return Math.max(0, Math.min(100, Math.round(progress * 100)));
}

export type JuzCell = { juz: number; memorized: number; learning: number; total: number; share: number; done: boolean };

/**
 * The thirty parts as a bar, from the hifz map: each part's memorized pages
 * (strong, fading or weak now) out of its pages; learning pages count apart.
 * A part the map does not carry is empty.
 */
export function juzStrip(juzs: readonly Juz[]): JuzCell[] {
  const byNumber = new Map(juzs.map((j) => [j.juz, j]));
  return Array.from({ length: JUZ_COUNT }, (_, i) => {
    const juz = byNumber.get(i + 1);
    const pages = juz?.pages ?? [];
    const memorized = pages.filter((p) => p.strength === 'strong' || p.strength === 'fading' || p.strength === 'weak').length;
    const learning = pages.filter((p) => p.strength === 'learning').length;
    const total = pages.length;
    const share = total === 0 ? 0 : memorized / total;
    return { juz: i + 1, memorized, learning, total, share, done: total > 0 && memorized === total };
  });
}

/** Whole parts memorized, from the strip. */
export function juzDone(strip: readonly JuzCell[]): number {
  return strip.filter((c) => c.done).length;
}

/** The kids' look shows how far along as stars (no numbers): five, filled by the share behind them, at least one once anything is. */
export function starsOf(progress: number, of = 5): { filled: number; of: number } {
  const share = Math.max(0, Math.min(1, progress || 0));
  const filled = share <= 0 ? 0 : Math.max(1, Math.round(share * of));
  return { filled, of };
}

/** A grade's tone: the best in the academy's colour, the middle as info, "acceptable" to attend to, "repeat" in danger. */
export function gradeTone(grade: string | null | undefined): Tone {
  switch (grade) {
    case 'excellent':
    case 'very_good':
      return 'brand';
    case 'good':
      return 'info';
    case 'acceptable':
      return 'attention';
    case 'repeat':
      return 'danger';
    default:
      return 'muted';
  }
}

/** An exam's tone: passed in the academy's colour, failed to attend to, booked as info, the rest quiet. */
export function examTone(status: ExamStatus | string): Tone {
  return ({ passed: 'brand', failed: 'attention', booked: 'info' } as Record<string, Tone>)[status] ?? 'muted';
}

/** The parts of a portion that are there today, in order (a Noorani lesson has its own line, not a part). */
export function partsOf(portion: Portion | null | undefined): PartKey[] {
  if (!portion) return [];
  return PARTS.filter((key) => portion[key] !== null && portion[key] !== undefined);
}

/** What the teacher or examiner says of one part while the form is open. */
export type PartDraft = {
  /** Recited today (a part left out is not sent). */
  recited: boolean;
  /** The counters were used: send them; untouched, the lesson's mushaf marks count instead. */
  counted: boolean;
  counts: Record<string, number>;
  /** The teacher's own grade; empty: the mistakes' grade. */
  grade: GradeKey | '';
  note: string;
};

export function emptyPart(types: readonly MistakeType[]): PartDraft {
  return { recited: true, counted: false, counts: Object.fromEntries(types.map((t) => [t.key, 0])), grade: '', note: '' };
}

/** A counter moved by one, never below 0 nor above the API's ceiling. */
export function bump(draft: PartDraft, kind: string, by: number, max = 200): PartDraft {
  const next = Math.max(0, Math.min(max, (draft.counts[kind] ?? 0) + by));
  return { ...draft, counted: true, counts: { ...draft.counts, [kind]: next } };
}

/** All the mistakes counted in a part. */
export function totalOf(draft: PartDraft): number {
  return Object.values(draft.counts).reduce((sum, n) => sum + n, 0);
}

/** One part as the API takes it: the counted mistakes (else null: the mushaf's marks), the grade chosen (else null) and a note. */
export function partRecord(draft: PartDraft): PartRecord {
  const note = draft.note.trim();
  return {
    mistakes: draft.counted ? Object.entries(draft.counts).map(([kind, count]) => ({ kind, count })) : null,
    grade: draft.grade === '' ? null : draft.grade,
    note: note === '' ? null : note,
  };
}

/** A student's recited parts as the API takes them (POST quran/lessons/{session}/result). */
export function partsRecord(drafts: Partial<Record<PartKey, PartDraft>>): Partial<Record<PartKey, PartRecord>> {
  const out: Partial<Record<PartKey, PartRecord>> = {};
  for (const key of PARTS) {
    const draft = drafts[key];
    if (draft?.recited) out[key] = partRecord(draft);
  }
  return out;
}

/** Whether a grade of its own needs the teacher's note (the API refuses one without it). */
export function needsNote(draft: Pick<PartDraft, 'grade' | 'note'>): boolean {
  return draft.grade !== '' && draft.note.trim() === '';
}

/** A list the API may send as an object keyed by id (an empty PHP array) or as a list. */
export function listOf<T>(value: readonly T[] | Record<string, T> | null | undefined): T[] {
  if (!value) return [];
  return Array.isArray(value) ? [...value] : Object.values(value);
}

/** The certificates a family sees: none while the academy keeps them, standing ones first, then revoked. */
export function shownCertificates(answer: { shown: boolean; certificates: Certificate[] | Record<string, Certificate> } | null | undefined): Certificate[] {
  if (!answer?.shown) return [];
  const all = listOf(answer.certificates);
  return [...all.filter((c) => !c.revoked), ...all.filter((c) => c.revoked)];
}

/** A verification code as typed (any case, spaces, with or without dashes) in its printed form, or null when it cannot be one. */
export function cleanCode(code: string): string | null {
  const plain = decodeURIComponentSafe(code).toUpperCase().replace(/[^A-Z0-9]/g, '');
  if (plain.length !== 16) return null;
  return plain.match(/.{4}/g)!.join('-');
}

function decodeURIComponentSafe(value: string): string {
  try {
    return decodeURIComponent(value);
  } catch {
    return value;
  }
}

/** Pace fields as typed: a number, or null to go back to the program's. */
export function paceValue(raw: string, integer: boolean): number | null | 'bad' {
  const text = raw.trim();
  if (text === '') return null;
  const value = Number(text.replace(',', '.'));
  if (!Number.isFinite(value) || value < 0 || (integer && !Number.isInteger(value))) return 'bad';
  return value;
}

/** Pages as people read them: runs joined ("582–584، 590"). */
export function pagesText(pages: readonly number[]): string {
  const sorted = [...new Set(pages)].sort((a, b) => a - b);
  const runs: string[] = [];
  for (let i = 0; i < sorted.length; i++) {
    const start = sorted[i]!;
    let end = start;
    while (sorted[i + 1] === end + 1) end = sorted[++i]!;
    runs.push(start === end ? String(start) : `${start}–${end}`);
  }
  return runs.join('، ');
}

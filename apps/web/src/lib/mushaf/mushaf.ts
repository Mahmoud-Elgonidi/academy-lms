import { latinDigits } from '../hifz/hifz';
import type { SurahRef } from '../hifz/types';
import type { MarkKind, MushafPage, NotebookMark, NotebookVerse, WordMark, WordRef } from './types';

/**
 * The shared mushaf's plain logic (components/mushaf uses it): the page as
 * it is drawn (surah headers, the basmala, each word with its marks, the
 * verses' ends), each kind's colour from the academy's tokens, the teacher's
 * menu on a word, following the teacher's page, and which messages in the
 * lesson's room to act on; and the mistakes notebook's verses.
 */

export const KINDS: readonly MarkKind[] = ['wrong', 'hesitation', 'tajweed'];

/** A kind the portal knows, or null (one the API adds later shows as a plain mark). */
export function kindOf(value: string): MarkKind | null {
  return KINDS.includes(value as MarkKind) ? (value as MarkKind) : null;
}

/** A word marked in this lesson, in the academy's tokens: wrong in danger, a hesitation to attend to, tajweed as info. */
export function markClass(kind: string): string {
  const known = kindOf(kind);
  return known === null ? 'bg-line text-ink' : ({ wrong: 'bg-danger-soft text-danger', hesitation: 'bg-attention-soft text-attention', tajweed: 'bg-info-soft text-info' } as const)[known];
}

/** A word marked in an earlier lesson: a faint dotted underline in its kind's colour. */
export function earlierClass(kind: string): string {
  const known = kindOf(kind);
  const colour = known === null ? 'decoration-muted/50' : ({ wrong: 'decoration-danger/45', hesitation: 'decoration-attention/45', tajweed: 'decoration-info/45' } as const)[known];
  return `underline decoration-dotted decoration-2 underline-offset-[0.5em] ${colour}`;
}

/** A kind's square in the legend. */
export function swatchClass(kind: string): string {
  const known = kindOf(kind);
  return known === null ? 'bg-line' : ({ wrong: 'bg-danger', hesitation: 'bg-attention', tajweed: 'bg-info' } as const)[known];
}

/** One word's key: its surah, its verse and its place in the verse. */
export function wordKey(w: WordRef): string {
  return `${w.surah}:${w.verse}:${w.word}`;
}

/** Arabic-Indic numbers, as the mushaf writes them, whatever the page's language. */
const arabicDigits = new Intl.NumberFormat('ar-EG-u-nu-arab', { useGrouping: false });

export function arabicNumber(n: number): string {
  return arabicDigits.format(n);
}

/** A verse's end: its number between the ornate brackets (﴿١٢﴾). */
export function verseEnd(verse: number): string {
  return `﴿${arabicNumber(verse)}﴾`;
}

export type MushafWord = WordRef & {
  key: string;
  text: string;
  /** Its mark in this lesson. */
  mark: string | null;
  /** Its marks in earlier lessons: the latest kind and how many times. */
  earlier: { kind: string; times: number } | null;
};

export type MushafItem = ({ kind: 'word' } & MushafWord) | { kind: 'end'; key: string; surah: number; verse: number; label: string };

/** The page as it is drawn, in reading order: a surah's header before its first verse, the basmala under it, and runs of verses. */
export type MushafPiece =
  | { kind: 'surah'; key: string; surah: SurahRef }
  | { kind: 'basmala'; key: string; words: string[] }
  | { kind: 'text'; key: string; items: MushafItem[] };

/** Surahs whose first verse has no basmala of its own above it: al-Fatiha (its first verse is the basmala) and at-Tawba. */
const NO_BASMALA = new Set([1, 9]);

export function pageModel(page: Pick<MushafPage, 'verses' | 'surahs' | 'basmala' | 'marks' | 'earlier'>): MushafPiece[] {
  const marks = new Map(page.marks.map((m) => [wordKey(m), m.kind]));
  const earlier = new Map(page.earlier.map((m) => [wordKey(m), { kind: m.kind, times: m.times }]));
  const pieces: MushafPiece[] = [];
  let run: MushafItem[] = [];
  const close = () => {
    if (run.length > 0) pieces.push({ kind: 'text', key: `text-${run[0]!.key}`, items: run });
    run = [];
  };

  for (const v of page.verses) {
    if (v.verse === 1) {
      close();
      const surah = page.surahs.find((s) => s.number === v.surah) ?? { number: v.surah, name_ar: '', name_en: '' };
      pieces.push({ kind: 'surah', key: `surah-${v.surah}`, surah });
      if (page.basmala && page.basmala.length > 0 && !NO_BASMALA.has(v.surah)) pieces.push({ kind: 'basmala', key: `basmala-${v.surah}`, words: page.basmala });
    }
    v.words.forEach((text, i) => {
      const ref = { surah: v.surah, verse: v.verse, word: i + 1 };
      const key = wordKey(ref);
      run.push({ kind: 'word', ...ref, key, text, mark: marks.get(key) ?? null, earlier: earlier.get(key) ?? null });
    });
    run.push({ kind: 'end', key: `end-${v.surah}:${v.verse}`, surah: v.surah, verse: v.verse, label: verseEnd(v.verse) });
  }
  close();
  return pieces;
}

/** The page with a word's mark set (or taken off with null), as it will be once the API has it. */
export function withMark<P extends Pick<MushafPage, 'marks'>>(page: P, word: WordRef, kind: string | null): P {
  const key = wordKey(word);
  const others = page.marks.filter((m) => wordKey(m) !== key);
  const marks: WordMark[] = kind === null ? others : [...others, { surah: word.surah, verse: word.verse, word: word.word, kind }];
  return { ...page, marks };
}

/** The teacher's menu on a word: each kind, in the API's order (the one it has now chosen), and taking the mark off when there is one. */
export function wordMenu(kinds: Record<string, string>, current: string | null): { kinds: { kind: string; label: string; chosen: boolean }[]; clear: boolean } {
  return {
    kinds: Object.entries(kinds).map(([kind, label]) => ({ kind, label, chosen: kind === current })),
    clear: current !== null,
  };
}

/** Where the teacher is: the page they have open, else where the lesson starts (what the teacher's screen shows before turning). */
export function teacherPageOf(page: Pick<MushafPage, 'teacher_page' | 'start_page'>): number {
  return page.teacher_page ?? page.start_page;
}

/** Whether a learner is looking at another page than the teacher's (the way back is offered). The teacher never is. */
export function isAway(page: Pick<MushafPage, 'role' | 'page' | 'teacher_page' | 'start_page'>, following: boolean): boolean {
  return page.role !== 'teacher' && !following && page.page !== teacherPageOf(page);
}

/** The page to ask for: none while following (the API answers the teacher's page), else the one being looked at. The teacher asks for their own. */
export function pageToAsk(role: string, following: boolean, shown: number): number | undefined {
  return role === 'teacher' || following ? undefined : shown;
}

/** Following after an answer: asked for none, or the page looked at is the teacher's now (their next turn takes the learner along). */
export function followsAfter(answer: Pick<MushafPage, 'page' | 'teacher_page' | 'start_page'>, asked: number | undefined): boolean {
  return asked === undefined || answer.page === teacherPageOf(answer);
}

/** A page number as typed (Arabic-Indic digits too), when it is one of the mushaf's. */
export function pageInput(text: string, total: number): number | null {
  const typed = latinDigits(text.trim());
  if (!/^\d{1,4}$/.test(typed)) return null;
  const page = Number(typed);
  return page >= 1 && page <= total ? page : null;
}

/** The page before or after this one, or null at either end. */
export function stepPage(page: number, delta: number, total: number): number | null {
  const next = page + delta;
  return next >= 1 && next <= total ? next : null;
}

/** The lesson room's messages about the mushaf (LiveKit's data channel): a nudge to read the page again, nothing more. */
export const MUSHAF_TOPIC = 'mushaf';

export function mushafNudge(): Uint8Array<ArrayBuffer> {
  return new TextEncoder().encode(JSON.stringify({ v: 1 }));
}

/**
 * Whether a room message came from the lesson's teacher: the participant's
 * metadata, which the server signs into their seat ({role, user_id}), says
 * so. Nothing else in the message is trusted.
 */
export function sentByTeacher(metadata: string | null | undefined): boolean {
  if (!metadata) return false;
  try {
    const parsed: unknown = JSON.parse(metadata);
    return typeof parsed === 'object' && parsed !== null && (parsed as { role?: unknown }).role === 'teacher';
  } catch {
    return false;
  }
}

/** Whether to read the page again for a room message: on the mushaf's topic, from the teacher. */
export function actsOnRoomMessage(topic: string | undefined, metadata: string | null | undefined): boolean {
  return topic === MUSHAF_TOPIC && sentByTeacher(metadata);
}

// The mistakes notebook.

/** Of a word's kinds, the one that weighs most: wrong, then a hesitation, then tajweed. */
export function strongestKind(kinds: readonly string[]): string {
  return KINDS.find((k) => kinds.includes(k)) ?? kinds[0] ?? 'wrong';
}

/** A marked word in the notebook: by its weightiest kind, or for a child, all gently in the academy's colour. */
export function notebookClass(kinds: readonly string[], gentle: boolean): string {
  return gentle ? 'bg-brand-soft text-brand-strong' : markClass(strongestKind(kinds));
}

/** A word's kinds in the reader's words, weightiest first. */
export function kindLabels(kinds: readonly string[], labels: Record<string, string>): string[] {
  const order = (k: string) => {
    const i = KINDS.indexOf(k as MarkKind);
    return i < 0 ? KINDS.length : i;
  };
  return [...new Set(kinds)].sort((a, b) => order(a) - order(b)).map((k) => labels[k] ?? k);
}

export type NotebookToken = { kind: 'word'; place: number; text: string; mark: NotebookMark | null } | { kind: 'end'; label: string };

/** A verse of the notebook as it reads: its words, the marked ones with their marks, and its end. */
export function notebookTokens(verse: NotebookVerse): NotebookToken[] {
  const marks = new Map(verse.marks.map((m) => [m.word, m]));
  return [...verse.words.map((text, i): NotebookToken => ({ kind: 'word', place: i + 1, text, mark: marks.get(i + 1) ?? null })), { kind: 'end', label: verseEnd(verse.verse) }];
}

import type { HomeworkAccepts } from './types';

/** A passage of the Quran a homework asks to recite (title: as the API writes it). */
export type Expected = { surah: number; from: number; to: number; title?: string };

/** One of the Quran's surahs, as GET teacher/texts/quran gives them. */
export type Surah = { number: number; name_ar: string; name_en: string; verses: number };

export type WordState = 'ok' | 'missing' | 'wrong';

/** A recording's check, word by word, as the teacher's homework answers give it (never the family's). */
export type RecitationCheck = {
  /** pending, ready, failed */
  status: 'pending' | 'ready' | 'failed';
  status_label: string;
  failure: string | null;
  summary: { words: number; ok: number; missing: number; wrong: number; extra: number } | null;
  summary_label: string | null;
  verses: { surah: number | null; verse: number | null; words: { text: string; state: WordState; heard: string | null }[] }[];
  /** Words said that are not in the text, after the word they followed (counted from 1 through the passage; 0 before the first). */
  extra: { after: number; text: string }[];
  heard: string | null;
  note: string;
};

/** Homework that may come with a recording, so its recitation may be checked. */
export const RECITE_ACCEPTS: readonly HomeworkAccepts[] = ['audio', 'any'];

export const recitable = (accepts: HomeworkAccepts) => RECITE_ACCEPTS.includes(accepts);

/**
 * What the report sends for the homework's recitation: nothing while the form does not ask it
 * (the helper off and none set), else the passage while the switch is on, or none.
 */
export function expectedPayload(offered: boolean, on: boolean, accepts: HomeworkAccepts, value: Partial<Expected>): { expected?: Expected | null } {
  if (!offered) return {};
  if (!on || !recitable(accepts) || !value.surah || !value.from || !value.to) return { expected: null };
  return { expected: { surah: value.surah, from: value.from, to: value.to } };
}

/** How a surah reads in the picker: its number and both names. */
export const surahLabel = (s: Surah) => `${s.number}. ${s.name_ar} · ${s.name_en}`;

export type Token =
  | { kind: 'word'; text: string; state: WordState; heard: string | null }
  | { kind: 'extra'; text: string }
  | { kind: 'verse'; number: number };

/** The check as it reads: the text's words in order, each extra word after the one it followed, and each verse's number at its end. */
export function tokensOf(check: Pick<RecitationCheck, 'verses' | 'extra'>): Token[] {
  const extra = new Map<number, string[]>();
  for (const e of check.extra) extra.set(e.after, [...(extra.get(e.after) ?? []), e.text]);
  const after = (n: number): Token[] => (extra.get(n) ?? []).map((text) => ({ kind: 'extra', text }));

  const tokens: Token[] = after(0);
  let n = 0;
  for (const v of check.verses) {
    for (const w of v.words) {
      n += 1;
      tokens.push({ kind: 'word', text: w.text, state: w.state, heard: w.heard }, ...after(n));
    }
    if (v.verse) tokens.push({ kind: 'verse', number: v.verse });
  }
  return tokens;
}

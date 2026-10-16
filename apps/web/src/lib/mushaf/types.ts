import type { SurahRef } from '../hifz/types';
import type { LocalTime } from '../time';

/**
 * What the shared mushaf's endpoints answer, as the screens read them:
 * GET/PUT learning/lessons/{session}/mushaf and POST/DELETE its mistakes (a
 * page of a Quran lesson, its words, the words marked on it), and the
 * mistakes notebook (GET learning/mistakes for the family, GET
 * teacher/students/{enrollment}/mistakes for the teacher).
 */

/** What a marked word was: wrong, a hesitation, or tajweed (a kind the API adds later shows as a plain mark). */
export type MarkKind = 'wrong' | 'hesitation' | 'tajweed';

/** Who is looking: the lesson's teacher turns the pages and marks words; its student and their guardians follow and read. */
export type MushafRole = 'teacher' | 'student' | 'guardian';

/** A verse of the page, each word as written; a word is its surah, its verse and its place in the verse from 1. */
export type MushafVerse = { surah: number; verse: number; words: string[] };

/** A word marked in this lesson. */
export type WordMark = { surah: number; verse: number; word: number; kind: string };

/** A word on this page marked in the student's earlier lessons: the latest kind, and how many times. */
export type EarlierMark = WordMark & { times: number };

/** A word, as the mark and unmark calls name it. */
export type WordRef = { surah: number; verse: number; word: number };

/** A page of a lesson's shared mushaf. */
export type MushafPage = {
  session_id: string;
  role: MushafRole;
  /** The teacher, from the room's opening until the report is handed in. */
  can_mark: boolean;
  /** The page shown (1 to pages_total). */
  page: number;
  /** The page the teacher has open (the student's screen follows it); null until the teacher turns one. */
  teacher_page: number | null;
  /** Where the lesson starts. */
  start_page: number;
  pages_total: number;
  juz: number;
  /** As the API writes it in Arabic ("صفحة ١٢ · البقرة"). */
  title: string;
  surahs: SurahRef[];
  /** The basmala's words from the platform's copy, for a surah that begins on this page (not al-Fatiha, not at-Tawba); null when none does. */
  basmala: string[] | null;
  verses: MushafVerse[];
  marks: WordMark[];
  earlier: EarlierMark[];
  /** Each kind's name in the reader's language, in the API's order. */
  kinds: Record<string, string>;
  /** Shown under the text. */
  attribution: string;
};

/** A marked word of the notebook: its place in the verse (from 1), how many times, what it was, when last. */
export type NotebookMark = { word: number; times: number; kinds: string[]; last_at: LocalTime };

export type NotebookVerse = { verse: number; words: string[]; marks: NotebookMark[] };

export type NotebookSurah = { surah: number; name_ar: string; name_en: string; words: number; verses: NotebookVerse[] };

/** The mistakes notebook: the words marked in the last six months, by surah and verse. */
export type Notebook = { words: number; surahs: NotebookSurah[]; kinds: Record<string, string> };

/** What a Server Action about the mushaf comes back with: the page as it stands now, or the API's words. */
export type MushafResult = { ok: true; mushaf: MushafPage } | { ok: false; message?: string };

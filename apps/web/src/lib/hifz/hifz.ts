import type { Tone } from '@/components/ui';
import type { Grade, HifzMapData, JourneyHifz, Juz, Mark, Strength, SurahRef, WirdPage } from './types';

/**
 * The hifz map's plain logic (the pages in lib/hifz and components/hifz use
 * it): each strength's colour from the academy's tokens, the legend with its
 * counts, today's review as the journey's card says it, the next page to
 * review, and the teacher's choices (pages typed as a list, surahs found by
 * name or number).
 */

export const STRENGTHS: readonly Strength[] = ['new', 'learning', 'strong', 'fading', 'weak'];

/** The legend's order: memorized first, from strong to weak, then learning and new. */
export const LEGEND: readonly Strength[] = ['strong', 'fading', 'weak', 'learning', 'new'];

export const GRADES: readonly Grade[] = [1, 2, 3, 4];

export const MARKS: readonly Mark[] = ['strong', 'good', 'weak'];

/** The pages of the mushaf (Madani). */
export const MUSHAF_PAGES = 604;

/** A strength the API may add later reads as new until the portal knows it. */
export function strengthOf(value: unknown): Strength {
  return STRENGTHS.includes(value as Strength) ? (value as Strength) : 'new';
}

/** Each strength's tone (badges): strong in the academy's colour, fading to attend to, weak in danger, learning as info, new as the line. */
export function strengthTone(strength: Strength): Tone {
  return ({ new: 'muted', learning: 'info', strong: 'brand', fading: 'attention', weak: 'danger' } as const)[strengthOf(strength)];
}

/** A page's square on the map, in the academy's tokens (globals.css). */
export function squareClass(strength: Strength): string {
  return ({ new: 'bg-line', learning: 'bg-info', strong: 'bg-brand', fading: 'bg-attention', weak: 'bg-danger' } as const)[strengthOf(strength)];
}

/** A grade's tone on the teacher's list of reviews: forgot in danger, hard to attend to, good and easy in the academy's colour. */
export function gradeTone(grade: number): Tone {
  return grade <= 1 ? 'danger' : grade === 2 ? 'attention' : 'brand';
}

/** How many pages each strength has now (new: the pages not on the map yet). */
export function legendOf(map: Pick<HifzMapData, 'pages_total' | 'counts' | 'strengths'>): { strength: Strength; label: string; count: number }[] {
  const { counts } = map;
  const count: Record<Strength, number> = {
    strong: counts.strong,
    fading: counts.fading,
    weak: counts.weak,
    learning: counts.learning,
    new: Math.max(0, map.pages_total - counts.memorized - counts.learning),
  };
  return LEGEND.map((strength) => ({ strength, label: map.strengths[strength] ?? strength, count: count[strength] }));
}

/** The widest part's pages: the map's columns, so every page keeps one size. */
export function columnsOf(juzs: readonly Juz[]): number {
  return Math.max(1, ...juzs.map((j) => j.pages.length));
}

/** Today's review as the journey's card says it: pages left, or done for today, or nothing today; and how far (0–100). */
export function wirdStatus(hifz: JourneyHifz): { state: 'left' | 'done' | 'none'; left: number; done: number; percent: number } {
  const left = Math.max(0, Number(hifz.due) || 0);
  const done = Math.max(0, Number(hifz.done_today) || 0);
  const state = left > 0 ? 'left' : done > 0 ? 'done' : 'none';
  return { state, left, done, percent: left + done === 0 ? 0 : Math.round((100 * done) / (left + done)) };
}

/** The page to go on to after this one: the first of today's review that is not it. */
export function nextPage(pages: readonly Pick<WirdPage, 'page'>[], current: number): number | null {
  return pages.find((p) => p.page !== current)?.page ?? null;
}

/**
 * How a page is named: the API's own title in Arabic ("صفحة ٥٨٢ · النبأ"),
 * else the page's word in the reader's language and its surahs' names.
 */
export function pageTitle(p: { page: number; title?: string; surahs: readonly SurahRef[] }, locale: string, pageWord: string): string {
  if (locale === 'ar' && p.title) return p.title;
  const names = p.surahs.map((s) => (locale === 'ar' ? s.name_ar : s.name_en)).join('، ');
  return names ? `${pageWord} · ${names}` : pageWord;
}

/** A page number from the address, when it is one of the mushaf's. */
export function pageParam(value: string, total = MUSHAF_PAGES): number | null {
  if (!/^\d{1,4}$/.test(value)) return null;
  const page = Number(value);
  return page >= 1 && page <= total ? page : null;
}

/** Arabic-Indic and Persian digits as Latin ones (a teacher may type "٥٨٢"). */
function latinDigits(text: string): string {
  return text.replace(/[٠-٩۰-۹]/g, (d) => String((d.charCodeAt(0) & 0xf) % 10));
}

/**
 * Pages typed as a list ("582، 583 590-592"): each number or range of the
 * mushaf's pages, in order and once; what cannot be read comes back as typed.
 */
export function parsePages(text: string, total = MUSHAF_PAGES): { pages: number[]; invalid: string[] } {
  const found = new Set<number>();
  const invalid: string[] = [];
  const parts = latinDigits(text)
    .split(/[\s,،;؛]+/)
    .map((p) => p.trim())
    .filter(Boolean);
  for (const part of parts) {
    const range = part.match(/^(\d{1,4})\s*[-–—]\s*(\d{1,4})$/) ?? part.match(/^(\d{1,4})$/);
    const from = range ? Number(range[1]) : NaN;
    const to = range ? Number(range[2] ?? range[1]) : NaN;
    if (!range || from < 1 || to > total || from > to) {
      invalid.push(part);
      continue;
    }
    for (let page = from; page <= to; page += 1) found.add(page);
  }
  return { pages: [...found].sort((a, b) => a - b), invalid };
}

/** What the teacher chose, as the API takes it: only the kinds chosen, numbers in order. */
export function choicePayload(choice: { juz?: Iterable<number>; surahs?: Iterable<number>; pages?: Iterable<number> }): { juz?: number[]; surahs?: number[]; pages?: number[] } {
  const sorted = (values?: Iterable<number>) => [...new Set(values ?? [])].sort((a, b) => a - b);
  const out: { juz?: number[]; surahs?: number[]; pages?: number[] } = {};
  for (const key of ['juz', 'surahs', 'pages'] as const) {
    const values = sorted(choice[key]);
    if (values.length > 0) out[key] = values;
  }
  return out;
}

/** How many pages a choice of parts, surahs and pages comes to (each page once), as the API will mark them. */
export function chosenPages(
  map: { juzs: readonly Juz[]; surahs: readonly { number: number; pages: readonly number[] }[] },
  choice: { juz: Iterable<number>; surahs: Iterable<number>; pages: Iterable<number> },
): number {
  const pages = new Set<number>(choice.pages);
  const juz = new Set(choice.juz);
  const surahs = new Set(choice.surahs);
  for (const j of map.juzs) if (juz.has(j.juz)) j.pages.forEach((p) => pages.add(p.page));
  for (const s of map.surahs) if (surahs.has(s.number)) s.pages.forEach((p) => pages.add(p));
  return pages.size;
}

/** Arabic as one types it to look for something: no vowel marks or tatweel, one alef, ta marbuta as ha, alef maqsura as ya. */
export function looseArabic(text: string): string {
  return text
    .replace(/[ً-ٰٟۖ-ۭـ]/g, '')
    .replace(/[أإآٱ]/g, 'ا')
    .replace(/ة/g, 'ه')
    .replace(/ى/g, 'ي');
}

/** Latin names without dashes, apostrophes or case ("Al-Baqarah" → "albaqarah"). */
function looseLatin(text: string): string {
  return text.toLowerCase().replace(/[^a-z0-9]/g, '');
}

/** The surahs a teacher's search finds: by number, or any part of the Arabic or English name, typed loosely. */
export function findSurahs<S extends SurahRef>(surahs: readonly S[], query: string): S[] {
  const asked = latinDigits(query.trim());
  if (asked === '') return [...surahs];
  if (/^\d+$/.test(asked)) return surahs.filter((s) => String(s.number).startsWith(asked));
  const arabic = looseArabic(asked).replace(/\s+/g, ' ');
  const latin = looseLatin(asked);
  return surahs.filter((s) => (arabic !== '' && looseArabic(s.name_ar).includes(arabic)) || (latin !== '' && looseLatin(s.name_en).includes(latin)));
}

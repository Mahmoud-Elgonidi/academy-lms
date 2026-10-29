import { describe, expect, it } from 'vitest';
import { choicePayload, chosenPages, columnsOf, findSurahs, gradeTone, legendOf, looseArabic, nextPage, pageParam, pageTitle, parsePages, squareClass, strengthOf, strengthTone, wirdStatus } from './hifz';

describe('the hifz map', () => {
  it('colours each page from the academy’s tokens, and reads a strength it does not know as new', () => {
    expect(squareClass('new')).toBe('bg-line');
    expect(squareClass('learning')).toBe('bg-info');
    expect(squareClass('strong')).toBe('bg-brand');
    expect(squareClass('fading')).toBe('bg-attention');
    expect(squareClass('weak')).toBe('bg-danger');
    expect(strengthTone('strong')).toBe('brand');
    expect(strengthTone('fading')).toBe('attention');
    expect(strengthTone('new')).toBe('muted');
    expect(strengthOf('glowing')).toBe('new');
    expect(squareClass('glowing' as never)).toBe('bg-line');
  });

  it('counts each strength for the legend, new being every page not on the map', () => {
    const legend = legendOf({
      pages_total: 604,
      counts: { memorized: 30, learning: 4, strong: 20, fading: 7, weak: 3, due: 5 },
      strengths: { strong: 'ثابت', fading: 'تحتاج إلى مراجعة قريبًا', weak: 'ضعيف', learning: 'قيد الحفظ' },
    });
    expect(legend).toEqual([
      { strength: 'strong', label: 'ثابت', count: 20 },
      { strength: 'fading', label: 'تحتاج إلى مراجعة قريبًا', count: 7 },
      { strength: 'weak', label: 'ضعيف', count: 3 },
      { strength: 'learning', label: 'قيد الحفظ', count: 4 },
      // No label from the API: the key, never an empty line.
      { strength: 'new', label: 'new', count: 570 },
    ]);
  });

  it('gives every part the widest part’s columns, so a page keeps one size', () => {
    const juz = (n: number, pages: number) => ({ juz: n, pages: Array.from({ length: pages }, (_, i) => ({ page: i + 1, strength: 'new' as const })) });
    expect(columnsOf([juz(1, 21), juz(2, 20), juz(30, 23)])).toBe(23);
    expect(columnsOf([])).toBe(1);
  });

  it('tones the teacher’s reviews by grade', () => {
    expect([1, 2, 3, 4].map(gradeTone)).toEqual(['danger', 'attention', 'brand', 'brand']);
  });
});

describe('today’s review', () => {
  it('says what is left, that it is done, or that there is nothing today', () => {
    expect(wirdStatus({ due: 2, done_today: 1, daily: 3 })).toEqual({ state: 'left', left: 2, done: 1, percent: 33 });
    expect(wirdStatus({ due: 0, done_today: 3, daily: 3 })).toEqual({ state: 'done', left: 0, done: 3, percent: 100 });
    expect(wirdStatus({ due: 0, done_today: 0, daily: 3 })).toEqual({ state: 'none', left: 0, done: 0, percent: 0 });
  });

  it('goes on to the next page that is not this one', () => {
    const pages = [{ page: 582 }, { page: 583 }];
    expect(nextPage(pages, 582)).toBe(583);
    expect(nextPage(pages, 10)).toBe(582);
    expect(nextPage([{ page: 582 }], 582)).toBeNull();
  });

  it('names a page by the API’s title in Arabic, else by its word and its surahs', () => {
    const page = { page: 582, title: 'صفحة ٥٨٢ · النبأ', surahs: [{ number: 78, name_ar: 'النبإ', name_en: "An-Naba'" }] };
    expect(pageTitle(page, 'ar', 'صفحة 582')).toBe('صفحة ٥٨٢ · النبأ');
    expect(pageTitle(page, 'en', 'Page 582')).toBe("Page 582 · An-Naba'");
    expect(pageTitle({ ...page, title: '' }, 'ar', 'صفحة 582')).toBe('صفحة 582 · النبإ');
    expect(pageTitle({ page: 3, surahs: [] }, 'en', 'Page 3')).toBe('Page 3');
  });

  it('opens only the mushaf’s pages from the address', () => {
    expect(pageParam('1')).toBe(1);
    expect(pageParam('604')).toBe(604);
    expect(pageParam('0')).toBeNull();
    expect(pageParam('605')).toBeNull();
    expect(pageParam('5a')).toBeNull();
    expect(pageParam('-3')).toBeNull();
  });
});

describe('what the teacher says the student knows', () => {
  it('reads pages typed as a list, with ranges and Arabic digits, each once and in order', () => {
    expect(parsePages('583, 582 ٥٩٠-٥٩٢، 582')).toEqual({ pages: [582, 583, 590, 591, 592], invalid: [] });
    expect(parsePages('')).toEqual({ pages: [], invalid: [] });
    expect(parsePages('3 700 9-4 x 1–2')).toEqual({ pages: [1, 2, 3], invalid: ['700', '9-4', 'x'] });
  });

  it('sends only the kinds chosen', () => {
    expect(choicePayload({ juz: new Set([30, 29]), surahs: [], pages: [5, 5, 4] })).toEqual({ juz: [29, 30], pages: [4, 5] });
    expect(choicePayload({})).toEqual({});
  });

  it('counts the pages a choice comes to, each once', () => {
    const map = {
      juzs: [{ juz: 30, pages: [582, 583, 584].map((page) => ({ page, strength: 'new' as const })) }],
      surahs: [{ number: 78, pages: [582, 583] }, { number: 1, pages: [1] }],
    };
    expect(chosenPages(map, { juz: [30], surahs: [78], pages: [584, 10] })).toBe(4);
    expect(chosenPages(map, { juz: [], surahs: [1], pages: [] })).toBe(1);
    expect(chosenPages(map, { juz: [], surahs: [], pages: [] })).toBe(0);
  });

  it('finds a surah by number or by its name typed loosely', () => {
    const surahs = [
      { number: 1, name_ar: 'الفاتحة', name_en: 'Al-Faatiha' },
      { number: 2, name_ar: 'البقرة', name_en: 'Al-Baqara' },
      { number: 12, name_ar: 'يوسف', name_en: 'Yusuf' },
      { number: 78, name_ar: 'النبإ', name_en: "An-Naba'" },
    ];
    expect(findSurahs(surahs, '').map((s) => s.number)).toEqual([1, 2, 12, 78]);
    expect(findSurahs(surahs, '1').map((s) => s.number)).toEqual([1, 12]);
    expect(findSurahs(surahs, '١٢').map((s) => s.number)).toEqual([12]);
    expect(findSurahs(surahs, 'بقره').map((s) => s.number)).toEqual([2]);
    expect(findSurahs(surahs, 'النبا').map((s) => s.number)).toEqual([78]);
    expect(findSurahs(surahs, 'baqara').map((s) => s.number)).toEqual([2]);
    expect(findSurahs(surahs, 'an naba').map((s) => s.number)).toEqual([78]);
    expect(looseArabic('الفَاتِحَة')).toBe('الفاتحه');
  });
});

import { describe, expect, it } from 'vitest';
import {
  actsOnRoomMessage,
  arabicNumber,
  earlierClass,
  followsAfter,
  isAway,
  kindLabels,
  kindOf,
  markClass,
  mushafNudge,
  notebookClass,
  notebookTokens,
  pageInput,
  pageModel,
  pageToAsk,
  sentByTeacher,
  stepPage,
  strongestKind,
  swatchClass,
  teacherPageOf,
  verseEnd,
  withMark,
  wordKey,
  wordMenu,
  type MushafItem,
  type MushafPiece,
} from './mushaf';
import type { MushafPage } from './types';

const kinds = { wrong: 'غلط', hesitation: 'تردد', tajweed: 'تجويد' };

/** Page 1 of the mushaf, shortened: al-Fatiha, whose first verse is the basmala. */
const fatiha = {
  verses: [
    { surah: 1, verse: 1, words: ['بِسْمِ', 'اللَّهِ', 'الرَّحْمَٰنِ', 'الرَّحِيمِ'] },
    { surah: 1, verse: 2, words: ['الْحَمْدُ', 'لِلَّهِ', 'رَبِّ', 'الْعَالَمِينَ'] },
  ],
  surahs: [{ number: 1, name_ar: 'الفاتحة', name_en: 'Al-Fatihah' }],
  basmala: null,
  marks: [],
  earlier: [],
};

/** The end of al-Masad and the start of al-Ikhlas, on one page. */
const twoSurahs = {
  verses: [
    { surah: 111, verse: 5, words: ['فِي', 'جِيدِهَا', 'حَبْلٌ', 'مِّن', 'مَّسَدٍ'] },
    { surah: 112, verse: 1, words: ['قُلْ', 'هُوَ', 'اللَّهُ', 'أَحَدٌ'] },
    { surah: 112, verse: 2, words: ['اللَّهُ', 'الصَّمَدُ'] },
  ],
  surahs: [
    { number: 111, name_ar: 'المسد', name_en: 'Al-Masad' },
    { number: 112, name_ar: 'الإخلاص', name_en: 'Al-Ikhlas' },
  ],
  basmala: ['بِسْمِ', 'اللَّهِ', 'الرَّحْمَٰنِ', 'الرَّحِيمِ'],
  marks: [{ surah: 112, verse: 1, word: 3, kind: 'wrong' }],
  earlier: [
    { surah: 112, verse: 1, word: 3, kind: 'hesitation', times: 2 },
    { surah: 111, verse: 5, word: 2, kind: 'tajweed', times: 1 },
  ],
};

const words = (pieces: MushafPiece[]) => pieces.flatMap((p) => (p.kind === 'text' ? p.items : [])).filter((i): i is Extract<MushafItem, { kind: 'word' }> => i.kind === 'word');

describe('a page of the shared mushaf, as it is drawn', () => {
  it('gives every word its key: surah, verse and place in the verse from 1', () => {
    expect(wordKey({ surah: 2, verse: 255, word: 7 })).toBe('2:255:7');
    const all = words(pageModel(twoSurahs));
    expect(all.map((w) => w.key)).toEqual(['111:5:1', '111:5:2', '111:5:3', '111:5:4', '111:5:5', '112:1:1', '112:1:2', '112:1:3', '112:1:4', '112:2:1', '112:2:2']);
    expect(all[7]).toMatchObject({ surah: 112, verse: 1, word: 3, text: 'اللَّهُ' });
    // Keys are unique on a page, so each word is its own element.
    expect(new Set(all.map((w) => w.key)).size).toBe(all.length);
  });

  it('puts a surah’s header before its first verse, and the basmala under it, except for al-Fatiha and at-Tawba', () => {
    expect(pageModel(twoSurahs).map((p) => p.kind)).toEqual(['text', 'surah', 'basmala', 'text']);
    const head = pageModel(twoSurahs)[1];
    expect(head).toMatchObject({ kind: 'surah', surah: { number: 112, name_ar: 'الإخلاص' } });
    expect(pageModel(twoSurahs)[2]).toMatchObject({ kind: 'basmala', words: twoSurahs.basmala });
    // Al-Fatiha: its header, no basmala of its own (its first verse is it).
    expect(pageModel(fatiha).map((p) => p.kind)).toEqual(['surah', 'text']);
    expect(pageModel({ ...fatiha, basmala: ['بِسْمِ'] }).map((p) => p.kind)).toEqual(['surah', 'text']);
    // At-Tawba never has one, even if a basmala came with the page.
    const tawba = { ...fatiha, verses: [{ surah: 9, verse: 1, words: ['بَرَاءَةٌ'] }], surahs: [{ number: 9, name_ar: 'التوبة', name_en: 'At-Tawbah' }], basmala: ['بِسْمِ'] };
    expect(pageModel(tawba).map((p) => p.kind)).toEqual(['surah', 'text']);
    // No basmala from the API: none drawn.
    expect(pageModel({ ...twoSurahs, basmala: null }).map((p) => p.kind)).toEqual(['text', 'surah', 'text']);
  });

  it('ends every verse with its number in Arabic-Indic digits between the ornate brackets', () => {
    expect(arabicNumber(255)).toBe('٢٥٥');
    expect(verseEnd(5)).toBe('﴿٥﴾');
    expect(verseEnd(12)).toBe('﴿١٢﴾');
    const ends = pageModel(twoSurahs).flatMap((p) => (p.kind === 'text' ? p.items.filter((i) => i.kind === 'end') : []));
    expect(ends.map((e) => (e.kind === 'end' ? e.label : ''))).toEqual(['﴿٥﴾', '﴿١﴾', '﴿٢﴾']);
  });

  it('finds each word’s mark in this lesson, and its earlier ones with how many times', () => {
    const all = words(pageModel(twoSurahs));
    const allah = all.find((w) => w.key === '112:1:3')!;
    expect(allah.mark).toBe('wrong');
    expect(allah.earlier).toEqual({ kind: 'hesitation', times: 2 });
    expect(all.find((w) => w.key === '111:5:2')).toMatchObject({ mark: null, earlier: { kind: 'tajweed', times: 1 } });
    expect(all.find((w) => w.key === '111:5:1')).toMatchObject({ mark: null, earlier: null });
  });

  it('names a surah it was not told about by its number, never leaving it out', () => {
    const pieces = pageModel({ ...twoSurahs, surahs: [] });
    expect(pieces[1]).toMatchObject({ kind: 'surah', surah: { number: 112, name_ar: '' } });
  });
});

describe('the marks’ colours', () => {
  it('come from the academy’s tokens: wrong in danger, a hesitation to attend to, tajweed as info', () => {
    expect(markClass('wrong')).toBe('bg-danger-soft text-danger');
    expect(markClass('hesitation')).toBe('bg-attention-soft text-attention');
    expect(markClass('tajweed')).toBe('bg-info-soft text-info');
    expect(swatchClass('tajweed')).toBe('bg-info');
    // A kind the API adds later shows as a plain mark.
    expect(kindOf('rhythm')).toBeNull();
    expect(markClass('rhythm')).toBe('bg-line text-ink');
    expect(swatchClass('rhythm')).toBe('bg-line');
  });

  it('draw an earlier lesson’s mark as a faint dotted underline in its kind’s colour', () => {
    expect(earlierClass('wrong')).toContain('decoration-dotted');
    expect(earlierClass('wrong')).toContain('decoration-danger/45');
    expect(earlierClass('hesitation')).toContain('decoration-attention/45');
    expect(earlierClass('rhythm')).toContain('decoration-muted/50');
  });
});

describe('the teacher’s menu on a word', () => {
  it('offers each kind in the API’s order, and taking the mark off only when there is one', () => {
    expect(wordMenu(kinds, null)).toEqual({
      kinds: [
        { kind: 'wrong', label: 'غلط', chosen: false },
        { kind: 'hesitation', label: 'تردد', chosen: false },
        { kind: 'tajweed', label: 'تجويد', chosen: false },
      ],
      clear: false,
    });
    const marked = wordMenu(kinds, 'hesitation');
    expect(marked.clear).toBe(true);
    expect(marked.kinds.filter((k) => k.chosen).map((k) => k.kind)).toEqual(['hesitation']);
  });

  it('draws a mark at once, before the API answers, and takes one off', () => {
    const page = { marks: [{ surah: 112, verse: 1, word: 3, kind: 'wrong' }] };
    expect(withMark(page, { surah: 112, verse: 1, word: 3 }, 'tajweed').marks).toEqual([{ surah: 112, verse: 1, word: 3, kind: 'tajweed' }]);
    expect(withMark(page, { surah: 112, verse: 2, word: 1 }, 'wrong').marks).toHaveLength(2);
    expect(withMark(page, { surah: 112, verse: 1, word: 3 }, null).marks).toEqual([]);
    // The page given stays as it was.
    expect(page.marks).toHaveLength(1);
  });
});

describe('following the teacher’s page', () => {
  const page = (over: Partial<MushafPage>): Pick<MushafPage, 'role' | 'page' | 'teacher_page' | 'start_page'> => ({ role: 'student', page: 12, teacher_page: 12, start_page: 3, ...over });

  it('is the page the teacher has open, else where the lesson starts', () => {
    expect(teacherPageOf({ teacher_page: 12, start_page: 3 })).toBe(12);
    expect(teacherPageOf({ teacher_page: null, start_page: 3 })).toBe(3);
  });

  it('asks for no page while following (the API answers the teacher’s), else the one being looked at; the teacher asks for their own', () => {
    expect(pageToAsk('student', true, 40)).toBeUndefined();
    expect(pageToAsk('guardian', false, 40)).toBe(40);
    expect(pageToAsk('teacher', false, 40)).toBeUndefined();
  });

  it('offers the way back while a learner looks at another page, never to the teacher', () => {
    expect(isAway(page({ page: 40 }), false)).toBe(true);
    expect(isAway(page({ page: 40 }), true)).toBe(false);
    expect(isAway(page({ page: 12 }), false)).toBe(false);
    expect(isAway(page({ page: 3, teacher_page: null }), false)).toBe(false);
    expect(isAway(page({ role: 'teacher', page: 40 }), false)).toBe(false);
  });

  it('follows again once the page looked at is the teacher’s, so the next turn takes the learner along', () => {
    expect(followsAfter(page({ page: 12 }), undefined)).toBe(true);
    expect(followsAfter(page({ page: 40 }), 40)).toBe(false);
    expect(followsAfter(page({ page: 40, teacher_page: 40 }), 40)).toBe(true);
    expect(followsAfter(page({ page: 3, teacher_page: null }), 3)).toBe(true);
  });

  it('reads a page number as typed, Arabic-Indic digits too, within the mushaf', () => {
    expect(pageInput(' 582 ', 604)).toBe(582);
    expect(pageInput('٥٨٢', 604)).toBe(582);
    expect(pageInput('0', 604)).toBeNull();
    expect(pageInput('605', 604)).toBeNull();
    expect(pageInput('12a', 604)).toBeNull();
    expect(pageInput('', 604)).toBeNull();
    expect(stepPage(1, -1, 604)).toBeNull();
    expect(stepPage(1, 1, 604)).toBe(2);
    expect(stepPage(604, 1, 604)).toBeNull();
  });
});

describe('the lesson room’s messages', () => {
  it('are acted on only from a participant the server signed as the teacher, on the mushaf’s topic', () => {
    expect(sentByTeacher(JSON.stringify({ role: 'teacher', user_id: 't1' }))).toBe(true);
    expect(sentByTeacher(JSON.stringify({ role: 'student', user_id: 's1' }))).toBe(false);
    expect(sentByTeacher(JSON.stringify({ role: 'observer', user_id: 'o1' }))).toBe(false);
    expect(sentByTeacher(JSON.stringify({ user: { role: 'teacher' } }))).toBe(false);
    expect(sentByTeacher(JSON.stringify(['teacher']))).toBe(false);
    expect(sentByTeacher('teacher')).toBe(false);
    expect(sentByTeacher('{not json')).toBe(false);
    expect(sentByTeacher('')).toBe(false);
    expect(sentByTeacher(undefined)).toBe(false);
    expect(sentByTeacher(null)).toBe(false);
    const teacher = JSON.stringify({ role: 'teacher', user_id: 't1' });
    expect(actsOnRoomMessage('mushaf', teacher)).toBe(true);
    expect(actsOnRoomMessage('chat', teacher)).toBe(false);
    expect(actsOnRoomMessage(undefined, teacher)).toBe(false);
    expect(actsOnRoomMessage('mushaf', JSON.stringify({ role: 'student' }))).toBe(false);
  });

  it('say only that something changed', () => {
    expect(JSON.parse(new TextDecoder().decode(mushafNudge()))).toEqual({ v: 1 });
  });
});

describe('the mistakes notebook', () => {
  const verse = {
    verse: 2,
    words: ['اللَّهُ', 'الصَّمَدُ'],
    marks: [{ word: 2, times: 3, kinds: ['tajweed', 'wrong'], last_at: { utc: '2026-10-01T18:00:00Z', local: '2026-10-01T21:00:00', timezone: 'Asia/Riyadh', label: '1 أكتوبر' } }],
  };

  it('reads each verse as written, its marked words with their marks, then its end', () => {
    const tokens = notebookTokens(verse);
    expect(tokens).toHaveLength(3);
    expect(tokens[0]).toMatchObject({ kind: 'word', place: 1, text: 'اللَّهُ', mark: null });
    expect(tokens[1]).toMatchObject({ kind: 'word', place: 2, text: 'الصَّمَدُ', mark: { times: 3 } });
    expect(tokens[2]).toEqual({ kind: 'end', label: '﴿٢﴾' });
  });

  it('colours a word by its weightiest kind, or for a child, gently in the academy’s colour', () => {
    expect(strongestKind(['tajweed', 'wrong'])).toBe('wrong');
    expect(strongestKind(['tajweed', 'hesitation'])).toBe('hesitation');
    expect(strongestKind(['rhythm'])).toBe('rhythm');
    expect(notebookClass(['tajweed', 'wrong'], false)).toBe('bg-danger-soft text-danger');
    expect(notebookClass(['tajweed', 'wrong'], true)).toBe('bg-brand-soft text-brand-strong');
  });

  it('says a word’s kinds in the reader’s words, weightiest first and each once', () => {
    expect(kindLabels(['tajweed', 'wrong', 'tajweed'], kinds)).toEqual(['غلط', 'تجويد']);
    expect(kindLabels(['rhythm', 'hesitation'], kinds)).toEqual(['تردد', 'rhythm']);
  });
});

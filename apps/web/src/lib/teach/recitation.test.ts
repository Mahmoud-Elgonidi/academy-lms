import { describe, expect, it } from 'vitest';
import { expectedPayload, recitable, surahLabel, tokensOf } from './recitation';

describe('the recitation check', () => {
  it('sends what to recite only when the form asks it', () => {
    const passage = { surah: 1, from: 2, to: 3 };
    expect(expectedPayload(false, true, 'audio', passage)).toEqual({});
    expect(expectedPayload(true, true, 'audio', { ...passage, title: 'x' })).toEqual({ expected: passage });
    expect(expectedPayload(true, true, 'any', passage)).toEqual({ expected: passage });
    // Off, on text homework, or half filled: none.
    expect(expectedPayload(true, false, 'audio', passage)).toEqual({ expected: null });
    expect(expectedPayload(true, true, 'text', passage)).toEqual({ expected: null });
    expect(expectedPayload(true, true, 'audio', { surah: 1, from: 2 })).toEqual({ expected: null });
    expect(recitable('file')).toBe(false);
    expect(surahLabel({ number: 2, name_ar: 'ب', name_en: 'B', verses: 7 })).toBe('2. ب · B');
  });

  it('reads the words in order, extra words after the one they followed, and verse numbers at their end', () => {
    const tokens = tokensOf({
      verses: [
        { surah: 1, verse: 2, words: [{ text: 'أ', state: 'ok', heard: null }, { text: 'ب', state: 'missing', heard: null }] },
        { surah: 1, verse: 3, words: [{ text: 'ج', state: 'wrong', heard: 'د' }] },
      ],
      extra: [
        { after: 0, text: 'قبل' },
        { after: 1, text: 'تاني' },
        { after: 3, text: 'آخر' },
      ],
    });
    expect(tokens).toEqual([
      { kind: 'extra', text: 'قبل' },
      { kind: 'word', text: 'أ', state: 'ok', heard: null },
      { kind: 'extra', text: 'تاني' },
      { kind: 'word', text: 'ب', state: 'missing', heard: null },
      { kind: 'verse', number: 2 },
      { kind: 'word', text: 'ج', state: 'wrong', heard: 'د' },
      { kind: 'extra', text: 'آخر' },
      { kind: 'verse', number: 3 },
    ]);
  });
});

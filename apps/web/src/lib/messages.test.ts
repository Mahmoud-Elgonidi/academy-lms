import { describe, expect, it } from 'vitest';
import ar from '../../messages/ar.json';
import en from '../../messages/en.json';

/** Every string in a messages file, with its path ("nav.kids.messages"). */
function strings(node: unknown, path = ''): [string, string][] {
  if (typeof node === 'string') return [[path, node]];
  return Object.entries(node as Record<string, unknown>).flatMap(([key, value]) => strings(value, path ? `${path}.${key}` : key));
}

// Modern Standard Arabic (docs/style/arabic.md), the kids' look in simple words:
// no colloquial word, and one word for one thing, as the API and the app check.
const COLLOQUIAL = [
  'ده', 'دي', 'دول', 'مش', 'مفيش', 'عشان', 'علشان', 'إزاي', 'ازاي', 'إيه', 'ايه', 'فين', 'ليه', 'دلوقتي', 'كده',
  'بتاع', 'بتاعة', 'بتاعك', 'اللي', 'عايز', 'عاوز', 'يلا', 'لسه', 'بقى', 'أوي', 'تاني', 'كمان', 'بس', 'خلّي', 'خلي',
  'قايمة', 'القايمة', 'دقايق', 'إمتى', 'امتى',
];
const GLOSSARY: [string, string][] = [
  ['(?:ال)?مدرّس(?:ين|ون)?', 'معلّم'],
  ['(?:ال)?مدرس(?:ين|ون)?', 'معلّم'],
  ['الفلوس', 'المدفوعات'],
  ['الكود', 'الرمز'],
  ['الدعم', 'المساعدة'],
  ['الستيكرز', 'الملصقات'],
];
const LETTER = '\\u0621-\\u064A\\u0671';
const word = (w: string) => new RegExp(`(?<![${LETTER}])${w}(?![${LETTER}])`, 'u');
const RULES: [RegExp, string][] = [
  ...COLLOQUIAL.map((w): [RegExp, string] => [word(w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')), 'a colloquial word']),
  ...GLOSSARY.map(([w, right]): [RegExp, string] => [word(w), `"${right}"`]),
];

describe('the portal’s words', () => {
  it('are Modern Standard Arabic, one word for one thing', () => {
    const wrong = strings(ar).flatMap(([path, text]) =>
      RULES.filter(([rule]) => rule.test(text)).map(([rule, why]) => `${path}: "${text.match(rule)![0]}" (${why})`),
    );
    expect(wrong).toEqual([]);
  });

  it('are there in both languages', () => {
    const keys = (messages: unknown) => strings(messages).map(([path]) => path).sort();
    expect(keys(en)).toEqual(keys(ar));
  });
});

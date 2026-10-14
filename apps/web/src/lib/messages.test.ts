import { describe, expect, it } from 'vitest';
import ar from '../../messages/ar.json';
import en from '../../messages/en.json';

/** Every string in a messages file, with its path ("nav.kids.messages"). */
function strings(node: unknown, path = ''): [string, string][] {
  if (typeof node === 'string') return [[path, node]];
  return Object.entries(node as Record<string, unknown>).flatMap(([key, value]) => strings(value, path ? `${path}.${key}` : key));
}

// The app's glossary (apps/mobile/tool/strings.py): one word for one thing.
// The kids' strings keep their own words.
const GLOSSARY: [RegExp, string][] = [
  [/الدعم/, 'المساعدة'],
  [/معلم/, 'مدرّس'],
  [/مدر(?!ّ)س(?!ة)/, 'مدرّس'],
  [/المدفوعات/, 'الفلوس'],
];

describe('the portal’s words', () => {
  it('say one thing one way, as the app does', () => {
    const wrong = strings(ar)
      .filter(([path]) => !/kids/i.test(path))
      .flatMap(([path, text]) => [
        ...GLOSSARY.filter(([word]) => word.test(text)).map(([word, right]) => `${path}: "${text.match(word)![0]}" → "${right}"`),
        // "القايمة" names the menu itself for screen readers; a tab or a title is "المزيد".
        ...(text.includes('القايمة') && !path.endsWith('.menu') && path !== 'menu' ? [`${path}: "القايمة" → "المزيد"`] : []),
      ]);
    expect(wrong).toEqual([]);
  });

  it('are there in both languages', () => {
    const keys = (messages: unknown) => strings(messages).map(([path]) => path).sort();
    expect(keys(en)).toEqual(keys(ar));
  });
});

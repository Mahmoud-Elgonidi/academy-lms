import { describe, expect, it } from 'vitest';
import ar from '../../messages/ar.json';
import en from '../../messages/en.json';
import adultsAr from '../../messages/adults/ar.json';
import adultsEn from '../../messages/adults/en.json';
import { refusedForAdults } from './adults-words';
import { audienceOf, overlaid, type MessageTree } from './audience';
import { isGuardian, mayAddChild, readsAsSelf } from './family/rules';
import { selfKey } from './site/report';
import { speaksToAdults } from '@/components/site/trial/words-path';

/*
 * An academy for adults (docs/architecture/02, section 29) reads the portal's
 * words with messages/adults/{locale}.json laid over the base: only keys the
 * base has, the same placeholders, and, over every screen a trainee, a
 * trainer, a payer or a visitor reads, no word of a school or a family.
 */

/** Every string under a node, with its path. */
function strings(node: unknown, path = ''): [string, string][] {
  if (typeof node === 'string') return [[path, node]];
  if (Array.isArray(node)) return node.flatMap((value, i) => strings(value, `${path}.${i}`));
  return Object.entries(node as Record<string, unknown>).flatMap(([key, value]) => strings(value, path ? `${path}.${key}` : key));
}
const at = (tree: unknown, path: string) => path.split('.').reduce<unknown>((node, key) => (node as Record<string, unknown> | undefined)?.[key], tree);
/** A message's placeholders ({name}, {n, plural, …}'s n), sorted. */
const placeholders = (text: string) => [...new Set([...text.matchAll(/\{\s*(\w+)\s*[,}]/g)].map((m) => m[1]))].sort();
/** A message as read, without its simple placeholders and tags. */
const said = (text: string) => text.replace(/\{\w+\}/g, ' ').replace(/<\/?\w+>/g, ' ');

const LOCALES = { ar: { base: ar, adults: adultsAr }, en: { base: en, adults: adultsEn } } as const;

// The places a trainee, a trainer, a payer or a visitor reads in an academy for adults (the public page's own words are site.adults.*, held by words.test.tsx).
const READ = ['common', 'signIn', 'nav.classic', 'family', 'teach', 'errors', 'roadmap', 'chat', 'support', 'recordings', 'account', 'dispute', 'myData', 'safeguarding', 'documents', 'games', 'journey', 'teachCard', 'zone', 'room', 'format', 'start', 'invoice', 'payReturn', 'balance', 'cards', 'areas', 'site.report'];
// What nobody reads there: a child's own look (kids, middle school, teens), a guardian's screens and consent, and the cheers from family (hidden).
const NEVER_SHOWN = [
  /\.kids\b/, /\.kids[A-Z]/, /^chat\.title\.(middle|teen)$/, /^nav\.classic\.privacy$/, /^myData\.young$/, /^support\.noneKids$/,
  /^safeguarding\.lead\.student$/, /^games\.guardian\./, /^journey\.(cheered|together\w*)$/, /^site\.report\.student\./, /^start\.consent\./, /^start\.learner\.forChild$/,
];

describe('the adults’ words', () => {
  it('reads the API’s audience, families by default', () => {
    expect(audienceOf('adults')).toBe('adults');
    expect(audienceOf('families')).toBe('families');
    expect(audienceOf(undefined)).toBe('families');
    expect(audienceOf('anything')).toBe('families');
  });

  it('lays the overlay over the base key by key, leaving the base untouched', () => {
    const base: MessageTree = { a: { b: 'B', c: 'C' }, d: 'D', list: ['x', 'y'] };
    expect(overlaid(base, { a: { b: 'b' }, list: ['z'] })).toEqual({ a: { b: 'b', c: 'C' }, d: 'D', list: ['z'] });
    expect(base).toEqual({ a: { b: 'B', c: 'C' }, d: 'D', list: ['x', 'y'] });
  });

  for (const [locale, { base, adults }] of Object.entries(LOCALES)) {
    it(`sets only keys the base has, with its placeholders: ${locale}`, () => {
      const wrong = strings(adults).flatMap(([path, text]) => {
        const under = at(base, path);
        if (typeof under !== 'string') return [`${path}: not in the base`];
        // A first-person sentence may leave out the learner's name; it never brings a value the page does not pass.
        const extra = placeholders(text).filter((p) => !placeholders(under).includes(p));
        return extra.length ? [`${path}: ${extra.join(', ')}`] : [];
      });
      expect(wrong).toEqual([]);
    });

    it(`says no school or family word on any screen an adult reads: ${locale}`, () => {
      const read = strings(overlaid(base as MessageTree, adults as MessageTree)).filter(
        ([path]) => READ.some((area) => path === area || path.startsWith(`${area}.`)) && !NEVER_SHOWN.some((rule) => rule.test(path)),
      );
      const wrong = read.flatMap(([path, text]) => refusedForAdults(said(text)).map((word) => `${path}: ${word}`));
      expect(wrong).toEqual([]);
    });

    it(`addresses the trainee alone, never as a family: ${locale}`, () => {
      if (locale !== 'ar') return;
      const plural = strings(adults).filter(([, text]) => /(?<![ء-ي])(?:لكم|معكم|أعجبكم|يعجبكم|لثقتكم|حسابكم|إليكم)(?![ء-ي])/u.test(text));
      expect(plural).toEqual([]);
    });
  }

  it('has the same keys in both languages', () => {
    const keys = (tree: unknown) => strings(tree).map(([path]) => path).sort();
    expect(keys(adultsEn)).toEqual(keys(adultsAr));
  });

  it('sees the words where they are, and lets the training words be', () => {
    expect(refusedForAdults('حصة ابنك مع المعلّم، وخصم الإخوة لأسرتك في الصف')).toEqual(['حصة', 'ابنك', 'المعلم', 'الإخوة', 'لأسرتك', 'الصف']);
    expect(refusedForAdults('جلسة مع المدرّب في قاعة الجلسة، ومهمة المتدرّب')).toEqual([]);
    expect(refusedForAdults('Your child’s lesson with the teacher')).toEqual(['child', 'lesson', 'teacher']);
    expect(refusedForAdults('Your session with the trainer')).toEqual([]);
  });
});

describe('who is offered what in an academy for adults', () => {
  const guardian = { stage: 'classic' as const, learners: [{ is_self: false }], roles: ['guardian'], adults: false };

  it('never offers adding a child there, nor counts anyone a guardian', () => {
    expect(mayAddChild(guardian)).toBe(true);
    expect(mayAddChild({ ...guardian, adults: true })).toBe(false);
    expect(isGuardian(guardian.learners)).toBe(true);
    expect(isGuardian(guardian.learners, true)).toBe(false);
  });

  it('offers adding a child to a guardian with none yet, never to an adult studying alone', () => {
    expect(mayAddChild({ ...guardian, learners: [] })).toBe(true);
    // The bug was here: no learners was read as a guardian.
    expect(mayAddChild({ ...guardian, learners: [], roles: ['student'] })).toBe(false);
    expect(mayAddChild({ ...guardian, learners: [{ is_self: true }], roles: ['student'] })).toBe(false);
    expect(mayAddChild({ ...guardian, stage: 'teen' })).toBe(false);
  });

  it('reads a report in the first person for a trainee, and for an adult reading their own', () => {
    expect(readsAsSelf(true, null)).toBe(true);
    expect(readsAsSelf(false, { is_self: true })).toBe(true);
    expect(readsAsSelf(false, { is_self: false })).toBe(false);
    const has = (key: string) => key === 'self.title';
    expect(selfKey(has, true, 'title')).toBe('self.title');
    expect(selfKey(has, true, 'kicker')).toBe('kicker');
    expect(selfKey(has, false, 'title')).toBe('title');
  });
});

describe('the public page and the trial links in an academy for adults', () => {
  const page = (audience: 'families' | 'adults') => ({ page: { settings: { audience } } }) as never;

  it('speak to adults whatever the page says, and a families academy follows its page', () => {
    expect(speaksToAdults(page('families'), { audience: 'adults' })).toBe(true);
    expect(speaksToAdults(null, { audience: 'adults' })).toBe(true);
    expect(speaksToAdults(page('adults'), { audience: 'families' })).toBe(true);
    expect(speaksToAdults(page('families'), { audience: 'families' })).toBe(false);
    expect(speaksToAdults(page('families'))).toBe(false);
  });
});

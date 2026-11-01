import { describe, expect, it } from 'vitest';
import type { Juz } from '../hifz/types';
import {
  bump,
  cleanCode,
  emptyPart,
  examTone,
  featureOff,
  gradeTone,
  juzDone,
  juzStrip,
  listOf,
  needsNote,
  pagesText,
  paceValue,
  partRecord,
  partsOf,
  partsRecord,
  percentOf,
  quranInPlan,
  shownCertificates,
  starsOf,
  totalOf,
} from './quran';
import type { Certificate, Portion } from './types';

const types = [
  { key: 'wrong', label: 'خطأ', weight: 1 },
  { key: 'tajweed', label: 'تجويد', weight: 0.5 },
];

describe('the Quran in the academy’s plan', () => {
  it('reads a 403 feature-not-in-plan as "not available", any other refusal not', () => {
    expect(featureOff({ status: 403, type: 'feature-not-in-plan' })).toBe(true);
    expect(featureOff({ status: 403, type: 'forbidden' })).toBe(false);
    expect(featureOff({ status: 404, type: 'feature-not-in-plan' })).toBe(false);
    expect(featureOff(null)).toBe(false);
  });

  it('follows me.plan_features', () => {
    expect(quranInPlan(['crm', 'quran'])).toBe(true);
    expect(quranInPlan(['crm'])).toBe(false);
    expect(quranInPlan(undefined)).toBe(false);
  });
});

describe('how far along', () => {
  it('writes the share as a whole percent between 0 and 100', () => {
    expect(percentOf(0.256)).toBe(26);
    expect(percentOf(1.2)).toBe(100);
    expect(percentOf(-1)).toBe(0);
    expect(percentOf(Number.NaN)).toBe(0);
  });

  it('draws the thirty parts from the hifz map, memorized apart from learning', () => {
    const juzs: Juz[] = [
      { juz: 30, pages: [{ page: 582, strength: 'strong' }, { page: 583, strength: 'weak' }, { page: 584, strength: 'learning' }, { page: 585, strength: 'new' }] },
      { juz: 1, pages: [{ page: 1, strength: 'fading' }, { page: 2, strength: 'strong' }] },
    ];
    const strip = juzStrip(juzs);
    expect(strip).toHaveLength(30);
    expect(strip[0]).toMatchObject({ juz: 1, memorized: 2, total: 2, share: 1, done: true });
    expect(strip[29]).toMatchObject({ juz: 30, memorized: 2, learning: 1, total: 4, share: 0.5, done: false });
    expect(strip[14]).toMatchObject({ juz: 15, total: 0, share: 0, done: false });
    expect(juzDone(strip)).toBe(1);
  });

  it('gives a child stars, never none once something is behind them', () => {
    expect(starsOf(0)).toEqual({ filled: 0, of: 5 });
    expect(starsOf(0.01)).toEqual({ filled: 1, of: 5 });
    expect(starsOf(0.5)).toEqual({ filled: 3, of: 5 });
    expect(starsOf(1)).toEqual({ filled: 5, of: 5 });
  });
});

describe('tones', () => {
  it('colours grades and exams from the academy’s tokens', () => {
    expect(gradeTone('excellent')).toBe('brand');
    expect(gradeTone('good')).toBe('info');
    expect(gradeTone('acceptable')).toBe('attention');
    expect(gradeTone('repeat')).toBe('danger');
    expect(gradeTone(null)).toBe('muted');
    expect(examTone('passed')).toBe('brand');
    expect(examTone('failed')).toBe('attention');
    expect(examTone('booked')).toBe('info');
    expect(examTone('suggested')).toBe('muted');
  });
});

describe('a lesson’s record', () => {
  it('takes the portion’s parts in order, only those there', () => {
    const part = { label: 'x', ranges: [], pages: [] };
    const portion: Portion = { new: part, near: null, far: part, lesson: null, stops: [], finished: false };
    expect(partsOf(portion)).toEqual(['new', 'far']);
    expect(partsOf(null)).toEqual([]);
  });

  it('counts mistakes by kind, never below 0 nor over the ceiling', () => {
    let draft = emptyPart(types);
    expect(draft.counted).toBe(false);
    draft = bump(draft, 'wrong', 1);
    draft = bump(draft, 'wrong', 1);
    draft = bump(draft, 'tajweed', -1);
    expect(draft.counts).toEqual({ wrong: 2, tajweed: 0 });
    expect(draft.counted).toBe(true);
    expect(totalOf(draft)).toBe(2);
    expect(bump(draft, 'wrong', 500, 200).counts.wrong).toBe(200);
  });

  it('sends untouched counters as null (the mushaf’s marks count), and the teacher’s own grade with its note', () => {
    expect(partRecord(emptyPart(types))).toEqual({ mistakes: null, grade: null, note: null });
    const counted = { ...bump(emptyPart(types), 'wrong', 3), grade: 'good' as const, note: '  أحسن في المدود ' };
    expect(partRecord(counted)).toEqual({ mistakes: [{ kind: 'wrong', count: 3 }, { kind: 'tajweed', count: 0 }], grade: 'good', note: 'أحسن في المدود' });
    expect(needsNote({ grade: 'good', note: ' ' })).toBe(true);
    expect(needsNote({ grade: '', note: '' })).toBe(false);
  });

  it('leaves out the parts not recited', () => {
    const recited = emptyPart(types);
    const skipped = { ...emptyPart(types), recited: false };
    expect(Object.keys(partsRecord({ new: recited, near: skipped }))).toEqual(['new']);
  });
});

describe('certificates', () => {
  const cert = (id: string, revoked: boolean): Certificate => ({
    id, number: `Q-2026-00000${id}`, title: 't', portion: { kind: 'juz', number: 30, label: 'جزء عمّ' }, riwaya: 'حفص', grade: 'excellent',
    grade_label: 'ممتاز', examiner: null, issued_at: null, revoked, url: revoked ? null : 'https://x', code: revoked ? null : 'ABCD-EFGH-JKMN-PQRS',
  });

  it('shows none while the academy keeps them, standing ones first', () => {
    expect(shownCertificates({ shown: false, certificates: [cert('1', false)] })).toEqual([]);
    expect(shownCertificates({ shown: true, certificates: [cert('1', true), cert('2', false)] }).map((c) => c.id)).toEqual(['2', '1']);
    expect(shownCertificates({ shown: true, certificates: {} })).toEqual([]);
    expect(listOf({ a: 1, b: 2 })).toEqual([1, 2]);
  });

  it('reads a code whatever its case or dashes, and refuses what cannot be one', () => {
    expect(cleanCode('abcd efgh-jkmn-pqrs')).toBe('ABCD-EFGH-JKMN-PQRS');
    expect(cleanCode('ABCD-EFGH-JKMN-PQRS')).toBe('ABCD-EFGH-JKMN-PQRS');
    expect(cleanCode('abcd%2Defgh%2Djkmn%2Dpqrs')).toBe('ABCD-EFGH-JKMN-PQRS');
    expect(cleanCode('short')).toBeNull();
  });
});

describe('pace', () => {
  it('takes a number, an empty field as the program’s, and refuses the rest', () => {
    expect(paceValue('3', true)).toBe(3);
    expect(paceValue('1,5', false)).toBe(1.5);
    expect(paceValue(' ', true)).toBeNull();
    expect(paceValue('1.5', true)).toBe('bad');
    expect(paceValue('-2', false)).toBe('bad');
    expect(paceValue('x', false)).toBe('bad');
  });
});

describe('pages', () => {
  it('writes runs of pages joined', () => {
    expect(pagesText([584, 582, 583, 590, 590])).toBe('582–584، 590');
    expect(pagesText([1])).toBe('1');
    expect(pagesText([])).toBe('');
  });
});

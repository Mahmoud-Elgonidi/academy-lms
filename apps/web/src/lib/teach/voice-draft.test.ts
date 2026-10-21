import { describe, expect, it } from 'vitest';
import type { Question } from '../report-answers';
import type { ReportItem } from './types';
import { draftAnswers, draftItems, fieldNames, marksOf, nearestDays, type VoiceDraft } from './voice-draft';

const item = (id: string, change: Partial<ReportItem> = {}): ReportItem => ({
  node_id: id,
  title: id,
  path: '',
  reason: 'next',
  objectives: null,
  outcome: null,
  score: null,
  note: null,
  ...change,
});

const draft = (change: Partial<VoiceDraft>): VoiceDraft => ({ heard: '', report: {}, filled: [], unsure: [], left: [], ...change });

describe('voice note drafts', () => {
  it('takes into a lesson only what the draft filled, and keeps the rest of its row', () => {
    const items = [item('a', { outcome: 'practiced', note: 'mine' }), item('b'), item('c')];
    const out = draftItems(items, draft({
      report: { items: [
        { node_id: 'a', label: null, outcome: 'mastered', score: 4, note: 'mine' },
        { node_id: 'b', label: null, outcome: 'introduced', score: null, note: 'new words' },
      ] },
      filled: ['items.a.outcome', 'items.a.score', 'items.b.outcome', 'items.b.note'],
    }));
    expect(out).toEqual([
      item('a', { outcome: 'mastered', score: 4, note: 'mine' }),
      item('b', { outcome: 'introduced', note: 'new words' }),
      item('c'),
    ]);
  });

  it('adds a lesson the teacher spoke of after the form\'s, titled from its path', () => {
    const out = draftItems([item('a')], draft({
      report: { items: [{ node_id: 'z', label: 'Level 1 › Unit 2 › Long vowels', outcome: 'practiced', score: null, note: null }] },
      filled: ['items.z'],
    }));
    expect(out.at(-1)).toEqual({ node_id: 'z', title: 'Long vowels', path: 'Level 1 › Unit 2', reason: null, objectives: null, outcome: 'practiced', score: null, note: null });
  });

  it('does not change what the draft did not fill, though the teacher typed meanwhile', () => {
    const out = draftItems([item('a', { outcome: 'needs_review' })], draft({ report: { items: [{ node_id: 'a', label: null, outcome: 'practiced', score: null, note: null }] }, filled: [] }));
    expect(out[0]!.outcome).toBe('needs_review');
  });

  it('marks fields suggested, and those the draft was unsure of to check, a lesson by its row', () => {
    expect(marksOf({ filled: ['overall', 'items.a.outcome', 'answers.pages', 'homework'], unsure: ['items.a.outcome', 'homework'] })).toEqual({
      overall: 'suggested',
      'items.a': 'check',
      'answers.pages': 'suggested',
      homework: 'check',
    });
  });

  it('gives back only the answers it filled', () => {
    expect(draftAnswers(draft({ report: { answers: { pages: 3, fluent: true } }, filled: ['answers.pages'] }))).toEqual({ pages: 3 });
  });

  it('puts the days to hand homework in on the nearest choice', () => {
    const choices = [1, 2, 3, 4, 5, 6, 7, 10, 14];
    expect(nearestDays(3, choices)).toBe(3);
    expect(nearestDays(9, choices)).toBe(10);
    expect(nearestDays(30, choices)).toBe(14);
  });

  it('names the fields as the teacher reads them, each once', () => {
    const question = { key: 'pages', label: 'Pages read' } as Question;
    const names = fieldNames(['overall', 'items.a', 'items.a.note', 'answers.pages', 'answers.gone', 'homework'], {
      fixed: { overall: 'Overall', homework: 'Homework', answers: 'Questions' },
      lessons: [{ id: 'a', label: 'Level 1 › Long vowels' }],
      questions: [question],
    });
    expect(names).toEqual(['Overall', 'Long vowels', 'Pages read', 'Questions', 'Homework']);
  });
});

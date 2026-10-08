import { describe, expect, it } from 'vitest';
import { answersOf, answersPayload, missingAnswers, type ReportTemplate } from './report-answers';

const template: ReportTemplate = {
  id: 't1',
  version: 2,
  fields: [
    { key: 'f1', label: 'Pages memorised', type: 'number', required: true, family: true, options: [], min: 0, max: 20, unit: 'pages' },
    { key: 'f2', label: 'Tajweed', type: 'choice', required: false, family: true, options: [{ key: 'o1', label: 'Excellent' }], min: null, max: null, unit: null },
    { key: 'f4', label: 'Revision', type: 'yes_no', required: true, family: true, options: [], min: null, max: null, unit: null },
    { key: 'f5', label: 'Note', type: 'text', required: false, family: false, options: [], min: null, max: null, unit: null },
  ],
};

describe('report answers', () => {
  it('starts the form from the answers given', () => {
    expect(answersOf([{ key: 'f1', label: 'Pages', type: 'number', value: 2.5, text: '2.5 pages', family: true, unit: 'pages', max: null }])).toEqual({ f1: 2.5 });
  });

  it('sends numbers as numbers, keeps false, and leaves empty answers and unknown keys out', () => {
    expect(answersPayload(template, { f1: '3', f2: 'o1', f4: false, f5: '  ', f9: 'x' })).toEqual({ f1: 3, f2: 'o1', f4: false });
    expect(answersPayload(null, { f1: 3 })).toEqual({});
  });

  it('names the required questions still open', () => {
    expect(missingAnswers(template, { f1: '', f4: null }).map((q) => q.key)).toEqual(['f1', 'f4']);
    expect(missingAnswers(template, { f1: 0, f4: false })).toEqual([]);
  });
});

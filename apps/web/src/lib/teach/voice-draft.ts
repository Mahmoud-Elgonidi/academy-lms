import type { AnswerValue, Question } from '../report-answers';
import type { Accepts, Outcome, ReportItem } from './types';

/**
 * A report draft from the teacher's voice note (POST teacher/reports/{report}/draft),
 * as the form takes it in. The API fills only what the open form left empty and
 * saves nothing; the teacher checks the draft and sends it as usual.
 */
export type DraftItem = { node_id: string; label: string | null; outcome: Outcome; score: number | null; note: string | null };

export type DraftHomework = { title: string; instructions: string | null; accepts: Accepts; due_days: number; practice?: boolean };

export type DraftReport = {
  engagement?: number | null;
  overall?: number | null;
  notes_for_student?: string | null;
  notes_internal?: string | null;
  absence_note?: string | null;
  items?: DraftItem[];
  answers?: Record<string, AnswerValue>;
  homework?: DraftHomework | null;
};

/** `filled`: the fields the draft filled (lessons as items.{id}, questions as answers.{key}); `unsure`: those to check first; `left`: what the report still needs. */
export type VoiceDraft = { heard: string; report: DraftReport; filled: string[]; unsure: string[]; left: string[] };

/** How a field shows it came from the draft: suggested, or suggested and worth a second look. */
export type Mark = 'suggested' | 'check';

/** The field a draft's name marks on the form: a lesson's outcome, score or note marks its row. */
function markedAt(field: string): string {
  return field.startsWith('items.') ? field.split('.').slice(0, 2).join('.') : field;
}

export function marksOf(draft: Pick<VoiceDraft, 'filled' | 'unsure'>): Record<string, Mark> {
  const marks: Record<string, Mark> = {};
  for (const field of draft.filled) marks[markedAt(field)] = 'suggested';
  for (const field of draft.unsure) if (marks[markedAt(field)]) marks[markedAt(field)] = 'check';
  return marks;
}

/**
 * The lessons on the form with the draft in them: each keeps its row (and the
 * plan's reason), and takes only what the draft filled, so what the teacher
 * set meanwhile stays; a lesson the teacher spoke of that is not there comes
 * in after them.
 */
export function draftItems<T extends ReportItem>(items: T[], draft: Pick<VoiceDraft, 'report' | 'filled'>): (T | ReportItem)[] {
  const filled = new Set(draft.filled);
  const drafted = draft.report.items ?? [];
  const said = new Map(drafted.map((d) => [d.node_id, d]));
  const kept = items.map((item) => {
    const d = said.get(item.node_id);
    if (!d) return item;
    const at = `items.${item.node_id}.`;
    return {
      ...item,
      outcome: filled.has(`${at}outcome`) ? d.outcome : item.outcome,
      score: filled.has(`${at}score`) ? d.score : item.score,
      note: filled.has(`${at}note`) ? d.note : item.note,
    };
  });
  const added = drafted
    .filter((d) => filled.has(`items.${d.node_id}`) && !items.some((i) => i.node_id === d.node_id))
    .map((d): ReportItem => {
      const parts = (d.label ?? '').split(' › ');
      return { node_id: d.node_id, title: parts.at(-1) || d.node_id, path: parts.slice(0, -1).join(' › '), reason: null, objectives: null, outcome: d.outcome, score: d.score, note: d.note };
    });
  return [...kept, ...added];
}

/** The answers the draft filled, by key (the others stay as the teacher has them). */
export function draftAnswers(draft: VoiceDraft): Record<string, AnswerValue> {
  const out: Record<string, AnswerValue> = {};
  for (const field of draft.filled) {
    if (!field.startsWith('answers.')) continue;
    const key = field.slice('answers.'.length);
    const value = draft.report.answers?.[key];
    if (value !== undefined) out[key] = value;
  }
  return out;
}

/** The nearest of the form's choices of days to hand homework in. */
export function nearestDays(days: number, choices: readonly number[]): number {
  return choices.reduce((best, n) => (Math.abs(n - days) < Math.abs(best - days) ? n : best), choices[0]!);
}

/**
 * The fields as the teacher reads them: the form's own labels, a lesson by its
 * title, a question by its words. Each once.
 */
export function fieldNames(fields: string[], words: { fixed: Record<string, string>; lessons: { id: string; label: string }[]; questions: Question[] }): string[] {
  const names = fields.map((field) => {
    const [head, key] = field.split('.');
    if (head === 'items') {
      const label = key ? words.lessons.find((l) => l.id === key)?.label : undefined;
      return label ? label.split(' › ').at(-1)! : (words.fixed.items ?? head);
    }
    if (head === 'answers') return words.questions.find((q) => q.key === key)?.label ?? words.fixed.answers ?? head;
    return words.fixed[head!] ?? head!;
  });
  return [...new Set(names)];
}

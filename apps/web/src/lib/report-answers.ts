/**
 * The academy's own questions in a subject's lesson report (its report
 * template) and their answers, as the API sends them: the teacher's form
 * gets `template` (null when the subject has none), every report its
 * `answers` (for the family, only those it may read).
 */
export type QuestionType = 'rating' | 'number' | 'choice' | 'yes_no' | 'text';

export type Question = {
  key: string;
  label: string;
  type: QuestionType;
  required: boolean;
  /** false: only the academy reads the answer. */
  family: boolean;
  options: { key: string; label: string }[];
  min: number | null;
  max: number | null;
  unit: string | null;
};

export type ReportTemplate = { id: string; version: number; fields: Question[] };

export type AnswerValue = number | string | boolean;

export type ReportAnswer = {
  key: string;
  label: string;
  type: QuestionType;
  value: AnswerValue;
  /** The answer written out: "4/5", "3 pages", the choice's words, yes or no. */
  text: string;
  family: boolean;
  unit: string | null;
  max: number | null;
};

export type Answers = Record<string, AnswerValue | null>;

/** What the form starts from: each answer already given, by key. */
export function answersOf(answers: ReportAnswer[]): Answers {
  return Object.fromEntries(answers.map((a) => [a.key, a.value]));
}

/** What goes to the API: empty answers left out, numbers as numbers, texts trimmed. */
export function answersPayload(template: ReportTemplate | null, values: Answers): Record<string, AnswerValue> {
  const out: Record<string, AnswerValue> = {};
  for (const q of template?.fields ?? []) {
    const value = values[q.key];
    if (value === null || value === undefined || (typeof value === 'string' && value.trim() === '')) continue;
    if (q.type === 'number' || q.type === 'rating') {
      const n = Number(value);
      if (!Number.isNaN(n)) out[q.key] = n;
    } else {
      out[q.key] = typeof value === 'string' ? value.trim() : value;
    }
  }
  return out;
}

/** The required questions still without an answer. */
export function missingAnswers(template: ReportTemplate | null, values: Answers): Question[] {
  const given = answersPayload(template, values);
  return (template?.fields ?? []).filter((q) => q.required && !(q.key in given));
}

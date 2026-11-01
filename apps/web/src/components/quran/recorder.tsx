'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { recordQuranExam, recordQuranLesson, saveQuranPace, type QuranStudentRecord } from '@/app/[locale]/teach/actions';
import { Icon } from '@/components/icons';
import { Button, Field, Input, Notice, Textarea, join } from '@/components/ui';
import { bump, emptyPart, needsNote, paceValue, partRecord, partsRecord, totalOf, type PartDraft } from '@/lib/quran/quran';
import type { Grade, MistakeType, Pace, PartKey } from '@/lib/quran/types';

/*
 * The teacher's and the examiner's Quran forms: counting mistakes by kind
 * with big buttons, a grade of their own (with a note, which the API asks
 * for), and sending once with one idempotency key per submit (a new one
 * after it went through). Untouched counters send nothing: the words marked
 * in the lesson's shared mushaf count instead.
 */

function firstProblem(done: { message?: string; errors?: Record<string, string[]> }, fallback: string): string {
  return Object.values(done.errors ?? {})[0]?.[0] ?? done.message ?? fallback;
}

/** One part's counters, grade and note. */
export function PartFields({ draft, onChange, types, grades, label, idPrefix }: {
  draft: PartDraft;
  onChange: (next: PartDraft) => void;
  types: MistakeType[];
  grades: Grade[];
  label: string;
  idPrefix: string;
}) {
  const t = useTranslations('quran.form');
  return (
    <div className="space-y-3">
      <fieldset>
        <legend className="mb-1.5 text-sm font-semibold">{t('mistakes')}</legend>
        <ul className="grid gap-2 sm:grid-cols-2">
          {types.map((type) => {
            const n = draft.counts[type.key] ?? 0;
            return (
              <li key={type.key} className="flex items-center justify-between gap-2 rounded-control border border-line bg-white px-3 py-1.5" data-mistake={type.key}>
                <span className="text-sm">{type.label}</span>
                <span className="flex items-center gap-1">
                  <button type="button" onClick={() => onChange(bump(draft, type.key, -1))} disabled={n === 0} aria-label={t('less', { label: type.label, part: label })} className="grid size-10 place-items-center rounded-control border border-line text-lg font-bold hover:bg-brand-soft disabled:opacity-40">
                    −
                  </button>
                  <output aria-live="polite" className="w-8 text-center text-lg font-bold tabular-nums" data-mistake-count={n}>
                    {n}
                  </output>
                  <button type="button" onClick={() => onChange(bump(draft, type.key, 1))} aria-label={t('more', { label: type.label, part: label })} className="grid size-10 place-items-center rounded-control bg-brand-soft text-lg font-bold text-brand-strong hover:bg-brand hover:text-white">
                    +
                  </button>
                </span>
              </li>
            );
          })}
        </ul>
        <p className="mt-1.5 text-xs text-muted">{draft.counted ? t('counted', { n: totalOf(draft) }) : t('fromMushaf')}</p>
      </fieldset>
      <fieldset>
        <legend className="mb-1.5 text-sm font-semibold">{t('grade')}</legend>
        <div role="radiogroup" aria-label={`${t('grade')} · ${label}`} className="flex flex-wrap gap-1.5">
          <GradeChip on={draft.grade === ''} onClick={() => onChange({ ...draft, grade: '' })}>
            {t('byMistakes')}
          </GradeChip>
          {grades.map((g) => (
            <GradeChip key={g.key} on={draft.grade === g.key} onClick={() => onChange({ ...draft, grade: g.key })}>
              {g.label}
            </GradeChip>
          ))}
        </div>
      </fieldset>
      {draft.grade !== '' ? (
        <Field label={t('note')} hint={t('noteHint')}>
          <Textarea id={`${idPrefix}-note`} value={draft.note} onChange={(e) => onChange({ ...draft, note: e.target.value })} rows={2} maxLength={500} />
        </Field>
      ) : null}
    </div>
  );
}

function GradeChip({ on, onClick, children }: { on: boolean; onClick: () => void; children: string }) {
  return (
    <button
      type="button"
      role="radio"
      aria-checked={on}
      onClick={onClick}
      className={join('min-h-10 rounded-full border px-3.5 text-sm font-semibold transition-colors', on ? 'border-brand bg-brand text-white' : 'border-line bg-white hover:border-brand-line')}
    >
      {children}
    </button>
  );
}

type StudentDraft = { on: boolean; parts: Partial<Record<PartKey, PartDraft>> };

/**
 * A Quran lesson's record: for each student not yet recorded, each part of
 * today's portion (recited or not), its mistakes and grade; sent together.
 */
export function QuranLessonForm({ session, students, parts, partLabels, types, grades }: {
  session: string;
  students: { id: string; name: string }[];
  parts: PartKey[];
  partLabels: Record<PartKey, string>;
  types: MistakeType[];
  grades: Grade[];
}) {
  const t = useTranslations('quran.form');
  const common = useTranslations('common');
  const fresh = (): Record<string, StudentDraft> =>
    Object.fromEntries(students.map((s) => [s.id, { on: true, parts: Object.fromEntries(parts.map((p) => [p, emptyPart(types)])) }]));
  const [drafts, setDrafts] = useState(fresh);
  const [key, setKey] = useState(() => crypto.randomUUID());
  const [message, setMessage] = useState<string>();
  const [pending, start] = useTransition();
  const group = students.length > 1;

  const setPart = (student: string, part: PartKey, next: PartDraft) =>
    setDrafts((all) => ({ ...all, [student]: { ...all[student]!, parts: { ...all[student]!.parts, [part]: next } } }));

  const records: QuranStudentRecord[] = students
    .filter((s) => drafts[s.id]?.on)
    .map((s) => ({ student_id: s.id, parts: partsRecord(drafts[s.id]!.parts) }))
    .filter((r) => Object.keys(r.parts).length > 0);
  const missingNote = students.some((s) => drafts[s.id]?.on && Object.values(drafts[s.id]!.parts).some((d) => d?.recited && needsNote(d)));

  return (
    <form
      className="space-y-4"
      data-quran-form
      onSubmit={(e) => {
        e.preventDefault();
        if (records.length === 0) return setMessage(t('nothing'));
        if (missingNote) return setMessage(t('noteNeeded'));
        start(async () => {
          const done = await recordQuranLesson(session, records, key);
          if (done.ok) {
            setMessage(undefined);
            setKey(crypto.randomUUID());
          } else {
            setMessage(firstProblem(done, common('problem')));
          }
        });
      }}
    >
      {students.map((student) => {
        const draft = drafts[student.id]!;
        return (
          <fieldset key={student.id} className="rounded-card border border-line bg-white p-4 sm:p-5" data-student={student.id}>
            <legend className="sr-only">{student.name}</legend>
            {group ? (
              <label className="mb-3 flex items-center justify-between gap-3">
                <span className="font-semibold">{student.name}</span>
                <span className="flex items-center gap-2 text-sm text-muted">
                  {t('recordThem')}
                  <input type="checkbox" className="size-5 accent-[var(--brand)]" checked={draft.on} onChange={(e) => setDrafts((all) => ({ ...all, [student.id]: { ...draft, on: e.target.checked } }))} />
                </span>
              </label>
            ) : null}
            {draft.on ? (
              <div className="space-y-4">
                {parts.map((part) => {
                  const pd = draft.parts[part]!;
                  return (
                    <section key={part} className="border-t border-line pt-4 first:border-0 first:pt-0" data-record-part={part}>
                      <label className="mb-3 flex items-center justify-between gap-3">
                        <span className="font-semibold text-brand-strong">{partLabels[part]}</span>
                        <span className="flex items-center gap-2 text-sm text-muted">
                          {t('recited')}
                          <input type="checkbox" className="size-5 accent-[var(--brand)]" checked={pd.recited} onChange={(e) => setPart(student.id, part, { ...pd, recited: e.target.checked })} />
                        </span>
                      </label>
                      {pd.recited ? (
                        <PartFields draft={pd} onChange={(next) => setPart(student.id, part, next)} types={types} grades={grades} label={partLabels[part]} idPrefix={`${student.id}-${part}`} />
                      ) : (
                        <p className="text-sm text-muted">{t('notRecited')}</p>
                      )}
                    </section>
                  );
                })}
              </div>
            ) : (
              <p className="text-sm text-muted">{t('skipped')}</p>
            )}
          </fieldset>
        );
      })}
      {message ? <Notice tone="danger">{message}</Notice> : null}
      <Button disabled={pending} wide>
        <Icon name="check" size={18} />
        {pending ? common('loading') : t('save')}
      </Button>
    </form>
  );
}

/** An exam's result: the mistakes over the stop, the examiner's grade and words, and a retake day for a fail. */
export function ExamResultForm({ exam, types, grades }: { exam: string; types: MistakeType[]; grades: Grade[] }) {
  const t = useTranslations('quran.form');
  const common = useTranslations('common');
  const [draft, setDraft] = useState(() => emptyPart(types));
  const [retake, setRetake] = useState('');
  const [key, setKey] = useState(() => crypto.randomUUID());
  const [message, setMessage] = useState<string>();
  const [done, setDone] = useState(false);
  const [pending, start] = useTransition();

  if (done) return <Notice tone="brand">{t('examSaved')}</Notice>;
  return (
    <form
      className="space-y-4 rounded-card border border-line bg-white p-4 sm:p-5"
      data-exam-form
      onSubmit={(e) => {
        e.preventDefault();
        if (needsNote(draft)) return setMessage(t('noteNeeded'));
        start(async () => {
          const record = partRecord(draft);
          const sent = await recordQuranExam(exam, { ...record, retake_on: retake || null }, key);
          if (sent.ok) {
            setDone(true);
            setKey(crypto.randomUUID());
          } else {
            setMessage(firstProblem(sent, common('problem')));
          }
        });
      }}
    >
      <PartFields draft={draft} onChange={setDraft} types={types} grades={grades} label={t('exam')} idPrefix={`exam-${exam}`} />
      {draft.grade === '' ? (
        <Field label={t('examNote')} hint={t('examNoteHint')}>
          <Textarea value={draft.note} onChange={(e) => setDraft({ ...draft, note: e.target.value })} rows={2} maxLength={1000} />
        </Field>
      ) : null}
      <Field label={t('retakeOn')} hint={t('retakeHint')}>
        <Input type="date" value={retake} onChange={(e) => setRetake(e.target.value)} />
      </Field>
      {message ? <Notice tone="danger">{message}</Notice> : null}
      <Button disabled={pending} wide>
        {pending ? common('loading') : t('saveExam')}
      </Button>
    </form>
  );
}

/** A student's own pace on their plan: empty goes back to the program's. */
export function PaceForm({ enrollment, pace, unit }: { enrollment: string; pace: Pace; unit: string }) {
  const t = useTranslations('quran.pace');
  const common = useTranslations('common');
  const [values, setValues] = useState({ new_amount: String(pace.new_amount), near_portions: String(pace.near_portions), far_pages: String(pace.far_pages) });
  const [message, setMessage] = useState<{ tone: 'brand' | 'danger'; text: string }>();
  const [pending, start] = useTransition();
  const fields = [
    { name: 'new_amount', label: t('newAmount', { unit }), integer: false },
    { name: 'near_portions', label: t('nearPortions'), integer: true },
    { name: 'far_pages', label: t('farPages'), integer: true },
  ] as const;

  return (
    <form
      className="space-y-3"
      data-pace-form
      onSubmit={(e) => {
        e.preventDefault();
        const read = Object.fromEntries(fields.map((f) => [f.name, paceValue(values[f.name], f.integer)])) as Record<(typeof fields)[number]['name'], number | null | 'bad'>;
        if (Object.values(read).includes('bad')) return setMessage({ tone: 'danger', text: t('bad') });
        start(async () => {
          const done = await saveQuranPace(enrollment, read as Record<string, number | null> as { new_amount: number | null; near_portions: number | null; far_pages: number | null });
          setMessage(done.ok ? { tone: 'brand', text: common('saved') } : { tone: 'danger', text: firstProblem(done, common('problem')) });
        });
      }}
    >
      <div className="grid gap-3 sm:grid-cols-3">
        {fields.map((f) => (
          <Field key={f.name} label={f.label}>
            <Input inputMode={f.integer ? 'numeric' : 'decimal'} name={f.name} value={values[f.name]} onChange={(e) => setValues({ ...values, [f.name]: e.target.value })} />
          </Field>
        ))}
      </div>
      <p className="text-xs text-muted">{t('hint')}</p>
      {message ? <Notice tone={message.tone}>{message.text}</Notice> : null}
      <Button disabled={pending}>{pending ? common('loading') : t('save')}</Button>
    </form>
  );
}

'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition, type ReactNode } from 'react';
import { submitReport, type ReportPayload } from '@/app/[locale]/teach/actions';
import { useRouter } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Badge, Button, Card, Field, Input, Notice, Select, Textarea, join } from '@/components/ui';
import { ReportAnswers } from '@/components/report-answers';
import { formProblem } from '@/lib/problems';
import { answersOf, answersPayload, missingAnswers, type Answers, type Question } from '@/lib/report-answers';
import { ACCEPTS, OUTCOMES, type HomeworkAccepts, type Outcome, type ReportForm, type ReportItem } from '@/lib/teach/types';

type Item = ReportItem & { noteOpen: boolean };

/**
 * A report as the teacher writes it: what happened with each lesson of
 * today's plan (and any added), focus and overall, words for the family and
 * for the academy, the subject's own questions, and homework. An absence
 * asks only for a note. The API
 * checks it again (SubmitSessionReport) and its words come back here.
 */
export function ReportEditor({ form }: { form: ReportForm }) {
  const t = useTranslations('teach');
  const common = useTranslations('common');
  const router = useRouter();
  const [pending, start] = useTransition();
  const absence = form.kind === 'absence';
  const locked = form.status === 'reviewed';
  const sent = form.status === 'submitted';
  const hw = form.homework;
  const homeworkLocked = hw !== null && (hw.status !== 'assigned' || hw.submissions.length > 0);

  const [items, setItems] = useState<Item[]>(form.items.map((i) => ({ ...i, noteOpen: Boolean(i.note) })));
  const [engagement, setEngagement] = useState<number | null>(form.engagement);
  const [overall, setOverall] = useState<number | null>(form.overall);
  const [forFamily, setForFamily] = useState(form.notes_for_student ?? '');
  const [internal, setInternal] = useState(form.notes_internal ?? '');
  const [absenceNote, setAbsenceNote] = useState(form.absence_note ?? '');
  const [giving, setGiving] = useState(hw !== null);
  const [title, setTitle] = useState(hw?.title ?? '');
  const [instructions, setInstructions] = useState(hw?.instructions ?? '');
  const [accepts, setAccepts] = useState<HomeworkAccepts>(hw?.accepts ?? 'any');
  // Homework handed in by playing (chosen in the panel) keeps that way when the report is saved again.
  const acceptsChoices: HomeworkAccepts[] = hw?.accepts === 'practice' ? [...ACCEPTS, 'practice'] : [...ACCEPTS];
  const [dueDays, setDueDays] = useState(3);
  const [answers, setAnswers] = useState<Answers>(answersOf(form.answers));
  const [problem, setProblem] = useState<{ message?: string; errors: Record<string, string[]> }>({ errors: {} });

  const error = (key: string) => problem.errors[key]?.[0];
  const update = (index: number, change: Partial<Item>) => setItems((all) => all.map((item, i) => (i === index ? { ...item, ...change } : item)));
  const options = form.lesson_options.filter((o) => !items.some((i) => i.node_id === o.id));

  const add = (id: string) => {
    const option = form.lesson_options.find((o) => o.id === id);
    if (!option) return;
    const parts = option.label.split(' › ');
    setItems((all) => [
      ...all,
      { node_id: id, title: parts.at(-1) ?? option.label, path: parts.slice(0, -1).join(' › '), reason: null, objectives: null, outcome: null, score: null, note: null, noteOpen: false },
    ]);
  };

  const send = () => {
    if (!absence && (engagement === null || overall === null)) {
      setProblem({ message: t('pickRatings'), errors: {} });
      return;
    }
    if (!absence && items.some((i) => i.outcome === null)) {
      setProblem({ message: t('planHint'), errors: {} });
      return;
    }
    const missing = absence ? [] : missingAnswers(form.template, answers);
    if (missing.length > 0) {
      setProblem({ message: t('answerRequired', { question: missing[0]!.label }), errors: {} });
      return;
    }
    const payload: ReportPayload = absence
      ? { absence_note: absenceNote.trim(), notes_internal: internal.trim() || null }
      : {
          engagement,
          overall,
          notes_for_student: forFamily.trim() || null,
          notes_internal: internal.trim() || null,
          items: items.map((i) => ({ node_id: i.node_id, outcome: i.outcome!, score: i.score, note: i.note?.trim() || null })),
          answers: answersPayload(form.template, answers),
        };
    if (!homeworkLocked) {
      payload.homework = giving && title.trim() ? { title: title.trim(), instructions: instructions.trim() || null, accepts, due_days: dueDays } : null;
    }
    start(async () => {
      const done = await submitReport(form.id, payload);
      if (done.ok) {
        router.push('/teach/reports?sent=1');
      } else {
        const errors = done.errors ?? {};
        setProblem({
          message: formProblem({ message: done.message ?? common('problem'), errors }, [
            'engagement', 'overall', 'notes_for_student', 'notes_internal', 'absence_note', 'items', 'homework.title', 'homework.instructions',
            ...(form.template?.fields ?? []).map((q) => `answers.${q.key}`),
          ]),
          errors,
        });
      }
    });
  };

  if (locked) {
    return (
      <div className="space-y-4">
        <Notice tone="muted">{t('reportLocked')}</Notice>
        {form.review_note ? (
          <Card>
            <p className="text-sm font-semibold text-muted">{t('academySaid')}</p>
            <p className="mt-1 whitespace-pre-line">{form.review_note}</p>
          </Card>
        ) : null}
        <ReadOnly form={form} />
      </div>
    );
  }

  return (
    <form
      className="space-y-5"
      onSubmit={(e) => {
        e.preventDefault();
        send();
      }}
    >
      {form.review_note ? (
        <Notice tone="info">
          <span className="font-semibold">{t('academySaid')}</span> {form.review_note}
        </Notice>
      ) : null}

      {absence ? (
        <Card>
          <Field label={t('absenceNote')} hint={t('absenceHint')} error={error('absence_note')}>
            <Textarea value={absenceNote} onChange={(e) => setAbsenceNote(e.target.value)} rows={4} required maxLength={1000} />
          </Field>
        </Card>
      ) : (
        <>
          <Card>
            <h2 className="font-semibold">{t('todaysPlan')}</h2>
            <p className="mb-4 text-sm text-muted">{t('planHint')}</p>
            <ol className="space-y-4">
              {items.map((item, index) => (
                <li key={item.node_id} className="rounded-control border border-line p-3.5">
                  <div className="flex items-start gap-2">
                    <div className="min-w-0 flex-1">
                      <p className="flex flex-wrap items-center gap-2 font-semibold">
                        {item.title}
                        {item.reason ? <Badge tone={item.reason === 'review' ? 'info' : 'brand'}>{item.reason === 'review' ? t('reasonReview') : t('reasonNext')}</Badge> : null}
                      </p>
                      {item.path ? <p className="text-xs text-muted">{item.path}</p> : null}
                      {objectivesOf(item).map((o) => (
                        <p key={o} className="mt-1 text-sm text-muted">
                          • {o}
                        </p>
                      ))}
                    </div>
                    <button
                      type="button"
                      onClick={() => setItems((all) => all.filter((_, i) => i !== index))}
                      className="grid size-9 shrink-0 place-items-center rounded-control text-muted hover:bg-danger-soft hover:text-danger"
                      aria-label={`${common('remove')}: ${item.title}`}
                    >
                      <Icon name="x" size={18} />
                    </button>
                  </div>
                  <Choice
                    label={item.title}
                    value={item.outcome}
                    options={OUTCOMES.map((o) => ({ value: o, label: t(`outcome_${o}`) }))}
                    onChange={(outcome) => update(index, { outcome: outcome as Outcome })}
                    error={error(`items.${index}.outcome`)}
                  />
                  <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <Scale label={t('itemScore')} value={item.score} onChange={(score) => update(index, { score })} optional />
                    {!item.noteOpen ? (
                      <button type="button" onClick={() => update(index, { noteOpen: true })} className="text-sm font-semibold text-brand-strong">
                        + {t('itemNote')}
                      </button>
                    ) : null}
                  </div>
                  {item.noteOpen ? (
                    <div className="mt-3">
                      <Field label={t('itemNote')} error={error(`items.${index}.note`)}>
                        <Input value={item.note ?? ''} onChange={(e) => update(index, { note: e.target.value })} maxLength={500} />
                      </Field>
                    </div>
                  ) : null}
                </li>
              ))}
            </ol>
            {error('items') ? <p className="mt-2 text-sm text-danger">{error('items')}</p> : null}
            {options.length > 0 && items.length < 10 ? (
              <div className="mt-4">
                <Select value="" onChange={(e) => add(e.target.value)} aria-label={t('addLesson')}>
                  <option value="">{`+ ${t('addLesson')}`}</option>
                  {options.map((o) => (
                    <option key={o.id} value={o.id}>
                      {o.label}
                    </option>
                  ))}
                </Select>
              </div>
            ) : null}
          </Card>

          <Card className="space-y-4">
            <Scale label={t('engagement')} value={engagement} onChange={setEngagement} error={error('engagement')} />
            <Scale label={t('overall')} value={overall} onChange={setOverall} error={error('overall')} />
            <Field label={t('notesForFamily')} error={error('notes_for_student')}>
              <Textarea value={forFamily} onChange={(e) => setForFamily(e.target.value)} rows={3} maxLength={3000} />
            </Field>
          </Card>

          {form.template && form.template.fields.length > 0 ? (
            <Card className="space-y-4">
              <div>
                <h2 className="font-semibold">{t('questionsTitle')}</h2>
                <p className="text-sm text-muted">{t('questionsHint')}</p>
              </div>
              {form.template.fields.map((q) => (
                <QuestionField
                  key={q.key}
                  question={q}
                  value={answers[q.key] ?? null}
                  onChange={(value) => setAnswers((all) => ({ ...all, [q.key]: value }))}
                  error={error(`answers.${q.key}`)}
                />
              ))}
            </Card>
          ) : null}
        </>
      )}

      <Card>
        <Field label={t('notesInternal')} hint={t('notesInternalHint')} error={error('notes_internal')}>
          <Textarea value={internal} onChange={(e) => setInternal(e.target.value)} rows={2} maxLength={3000} />
        </Field>
      </Card>

      <Card>
        {homeworkLocked && hw ? (
          <>
            <h2 className="font-semibold">{t('homeworkTitle')}</h2>
            <p className="mt-1">{hw.title}</p>
            <p className="mt-2 text-sm text-muted">{t('homeworkLocked')}</p>
          </>
        ) : (
          <>
            <label className="flex items-center gap-3 font-semibold">
              <input type="checkbox" checked={giving} onChange={(e) => setGiving(e.target.checked)} className="size-5 accent-[var(--color-brand)]" />
              {t('giveHomework')}
            </label>
            {giving ? (
              <div className="mt-4 space-y-4">
                <Field label={t('homeworkTitle')} error={error('homework.title')}>
                  <Input value={title} onChange={(e) => setTitle(e.target.value)} maxLength={200} required />
                </Field>
                <Field label={`${t('homeworkDetails')} ${common('optional')}`} error={error('homework.instructions')}>
                  <Textarea value={instructions} onChange={(e) => setInstructions(e.target.value)} rows={2} maxLength={3000} />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field label={t('acceptsLabel')}>
                    <Select value={accepts} onChange={(e) => setAccepts(e.target.value as HomeworkAccepts)}>
                      {acceptsChoices.map((a) => (
                        <option key={a} value={a}>
                          {t(`accepts_${a}`)}
                        </option>
                      ))}
                    </Select>
                  </Field>
                  <Field label={t('dueLabel')}>
                    <Select value={dueDays} onChange={(e) => setDueDays(Number(e.target.value))}>
                      {[1, 2, 3, 4, 5, 6, 7, 10, 14].map((n) => (
                        <option key={n} value={n}>
                          {t('dueIn', { n })}
                        </option>
                      ))}
                    </Select>
                  </Field>
                </div>
              </div>
            ) : null}
          </>
        )}
      </Card>

      {problem.message ? <Notice tone="danger">{problem.message}</Notice> : null}
      <div className="sticky bottom-16 z-10 -mx-1 rounded-card bg-paper/90 px-1 py-2 backdrop-blur lg:bottom-0">
        <Button wide disabled={pending}>
          {pending ? common('loading') : sent ? t('updateReport') : t('sendReport')}
        </Button>
      </div>
    </form>
  );
}

/** One of the academy's questions, by the kind of answer it takes. */
function QuestionField({ question: q, value, onChange, error }: { question: Question; value: Answers[string]; onChange: (v: Answers[string]) => void; error?: string }) {
  const t = useTranslations('teach');
  const common = useTranslations('common');
  const label = (
    <span className="inline-flex flex-wrap items-center gap-2">
      {q.label}
      {!q.required ? <span className="font-normal text-muted">{common('optional')}</span> : null}
      {!q.family ? (
        <Badge>
          <Icon name="lock" size={12} /> {common('academyOnly')}
        </Badge>
      ) : null}
    </span>
  );
  if (q.type === 'rating') {
    return <Scale label={label} name={q.label} value={typeof value === 'number' ? value : value ? Number(value) : null} onChange={onChange} optional={!q.required} error={error} />;
  }
  if (q.type === 'choice' || q.type === 'yes_no') {
    const options = q.type === 'choice' ? q.options.map((o) => ({ value: o.key, label: o.label })) : [{ value: 'yes', label: t('yes') }, { value: 'no', label: t('no') }];
    const current = q.type === 'yes_no' ? (value === true ? 'yes' : value === false ? 'no' : null) : typeof value === 'string' ? value : null;
    return (
      <div>
        <p className="text-sm font-semibold">{label}</p>
        <Choice
          label={q.label}
          value={current}
          options={options}
          onChange={(v) => onChange(q.type === 'yes_no' ? v === 'yes' : current === v && !q.required ? null : v)}
          error={error}
        />
      </div>
    );
  }
  if (q.type === 'number') {
    return (
      <Field label={label} error={error}>
        <div className="flex items-center gap-2">
          <Input
            type="number"
            inputMode="decimal"
            step="any"
            min={q.min ?? undefined}
            max={q.max ?? undefined}
            value={value === null || typeof value === 'boolean' ? '' : String(value)}
            onChange={(e) => onChange(e.target.value)}
            className="max-w-40"
          />
          {q.unit ? <span className="text-sm text-muted">{q.unit}</span> : null}
        </div>
      </Field>
    );
  }
  return (
    <Field label={label} error={error}>
      <Input value={typeof value === 'string' ? value : ''} onChange={(e) => onChange(e.target.value)} maxLength={500} />
    </Field>
  );
}

function objectivesOf(item: ReportItem): string[] {
  if (Array.isArray(item.objectives)) return item.objectives;
  return item.objectives ? [item.objectives] : [];
}

/** One choice among a few, as buttons (a radio group to assistive technology). */
function Choice({ label, value, options, onChange, error }: { label: string; value: string | null; options: { value: string; label: string }[]; onChange: (v: string) => void; error?: string }) {
  return (
    <div className="mt-3">
      <div role="radiogroup" aria-label={label} className="grid grid-cols-2 gap-1.5 sm:grid-cols-4">
        {options.map((o) => (
          <button
            key={o.value}
            type="button"
            role="radio"
            aria-checked={value === o.value}
            onClick={() => onChange(o.value)}
            className={join(
              'min-h-10 rounded-control border px-2 text-sm font-semibold transition-colors',
              value === o.value ? 'border-brand bg-brand text-white' : 'border-line bg-white text-ink hover:border-brand-line',
            )}
          >
            {o.label}
          </button>
        ))}
      </div>
      {error ? <p className="mt-1 text-sm text-danger">{error}</p> : null}
    </div>
  );
}

/** 1 to 5; an optional one can be cleared by choosing it again. */
function Scale({ label, name, value, onChange, optional, error }: { label: ReactNode; name?: string; value: number | null; onChange: (v: number | null) => void; optional?: boolean; error?: string }) {
  const t = useTranslations('teach');
  return (
    <div>
      <div className="flex flex-wrap items-center gap-3">
        <span className="text-sm font-semibold">{label}</span>
        <div role="radiogroup" aria-label={name ?? (typeof label === 'string' ? label : undefined)} className="flex gap-1">
          {[1, 2, 3, 4, 5].map((n) => (
            <button
              key={n}
              type="button"
              role="radio"
              aria-checked={value === n}
              aria-label={t('rate', { n })}
              onClick={() => onChange(optional && value === n ? null : n)}
              className={join(
                'grid size-9 place-items-center rounded-control border text-sm font-semibold tabular-nums transition-colors',
                value !== null && n <= value ? 'border-brand bg-brand-soft text-brand-strong' : 'border-line bg-white text-muted hover:border-brand-line',
                value === n && 'bg-brand text-white',
              )}
            >
              {n}
            </button>
          ))}
        </div>
      </div>
      {error ? <p className="mt-1 text-sm text-danger">{error}</p> : null}
    </div>
  );
}

/** A reviewed report, as it was sent. */
function ReadOnly({ form }: { form: ReportForm }) {
  const t = useTranslations('teach');
  return (
    <Card className="space-y-3">
      {form.kind === 'absence' ? (
        <p className="whitespace-pre-line">{form.absence_note}</p>
      ) : (
        <>
          <ul className="space-y-2">
            {form.items.map((i) => (
              <li key={i.node_id} className="flex flex-wrap items-center gap-2">
                <span className="font-semibold">{i.title}</span>
                {i.outcome ? <Badge tone="brand">{t(`outcome_${i.outcome}`)}</Badge> : null}
                {i.score ? <Badge>{t('rate', { n: i.score })}</Badge> : null}
              </li>
            ))}
          </ul>
          <p className="text-sm text-muted">
            {t('engagement')}: {form.engagement ?? '—'} · {t('overall')}: {form.overall ?? '—'}
          </p>
          {form.notes_for_student ? <p className="whitespace-pre-line">{form.notes_for_student}</p> : null}
          <ReportAnswers answers={form.answers} />
        </>
      )}
    </Card>
  );
}

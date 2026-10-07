'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { assessTrial } from '@/app/[locale]/teach/actions';
import { Button, Field, Input, Notice, Select, Textarea } from '@/components/ui';
import { formProblem } from '@/lib/problems';
import type { Trial } from '@/lib/teach/types';

/** After a trial: the level, the plan to recommend, and notes for the sales team (the CRM takes it from there). */
export function AssessForm({ trial, plans }: { trial: Trial; plans: { id: string; name: string }[] }) {
  const t = useTranslations('teach');
  const common = useTranslations('common');
  const [level, setLevel] = useState(trial.level ?? '');
  const [plan, setPlan] = useState(trial.plan_id ?? '');
  const [perWeek, setPerWeek] = useState(trial.per_week ? String(trial.per_week) : '');
  const [notes, setNotes] = useState(trial.assessment ?? '');
  const [state, setState] = useState<{ ok?: boolean; message?: string; errors?: Record<string, string[]> }>({});
  const [pending, start] = useTransition();
  const error = (key: string) => state.errors?.[key]?.[0];

  return (
    <form
      className="space-y-4"
      onSubmit={(e) => {
        e.preventDefault();
        start(async () => {
          const done = await assessTrial(trial.id, { level: level.trim(), plan_id: plan || null, per_week: perWeek ? Number(perWeek) : null, notes: notes.trim() });
          setState(done.ok ? { ok: true } : { message: formProblem({ message: done.message ?? common('problem'), errors: done.errors }, ['level', 'plan_id', 'per_week', 'notes']), errors: done.errors });
        });
      }}
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label={t('trialLevel')} error={error('level')}>
          <Input value={level} onChange={(e) => setLevel(e.target.value)} maxLength={120} required />
        </Field>
        <Field label={t('trialPerWeek')} error={error('per_week')}>
          <Select value={perWeek} onChange={(e) => setPerWeek(e.target.value)}>
            <option value="">—</option>
            {[1, 2, 3, 4, 5, 6, 7].map((n) => (
              <option key={n} value={n}>
                {n}
              </option>
            ))}
          </Select>
        </Field>
      </div>
      <Field label={t('trialPlan')} error={error('plan_id')}>
        <Select value={plan} onChange={(e) => setPlan(e.target.value)}>
          <option value="">{t('trialNoPlan')}</option>
          {plans.map((p) => (
            <option key={p.id} value={p.id}>
              {p.name}
            </option>
          ))}
        </Select>
      </Field>
      <Field label={t('trialNotes')} error={error('notes')}>
        <Textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={3} maxLength={2000} required />
      </Field>
      {state.ok ? <Notice tone="brand">{t('assessed')}</Notice> : null}
      {state.message ? <Notice tone="danger">{state.message}</Notice> : null}
      <Button disabled={pending}>{pending ? common('loading') : t('saveAssessment')}</Button>
    </form>
  );
}

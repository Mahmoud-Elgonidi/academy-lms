'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { requestTimeOff, withdrawTimeOff, type TimeOffPayload } from '@/app/[locale]/teach/actions';
import { Button, Field, Input, Notice, Select, Textarea, join } from '@/components/ui';
import { formProblem } from '@/lib/problems';

const REASONS = ['sick', 'vacation', 'personal', 'family', 'emergency', 'other'] as const;

/** Whole days, or part of one day, on the teacher's own clock; the academy answers. */
export function TimeOffForm() {
  const t = useTranslations('teach');
  const common = useTranslations('common');
  const [part, setPart] = useState(false);
  const [first, setFirst] = useState('');
  const [last, setLast] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [reason, setReason] = useState('');
  const [note, setNote] = useState('');
  // One key per request: a retry of the same submit counts once.
  const [key, setKey] = useState(() => crypto.randomUUID());
  const [state, setState] = useState<{ ok?: boolean; message?: string; errors?: Record<string, string[]> }>({});
  const [pending, start] = useTransition();
  const error = (k: string) => state.errors?.[k]?.[0];

  const submit = () => {
    const payload: TimeOffPayload = { first_day: first, reason };
    if (part) Object.assign(payload, { from, to });
    else if (last && last !== first) payload.last_day = last;
    if (note.trim()) payload.note = note.trim();
    start(async () => {
      const done = await requestTimeOff(payload, key);
      if (done.ok) {
        setState({ ok: true });
        setFirst('');
        setLast('');
        setFrom('');
        setTo('');
        setReason('');
        setNote('');
        setKey(crypto.randomUUID());
      } else {
        setState({ message: formProblem({ message: done.message ?? common('problem'), errors: done.errors }, part ? ['first_day', 'from', 'to', 'reason', 'note'] : ['first_day', 'last_day', 'reason', 'note']), errors: done.errors });
      }
    });
  };

  return (
    <form
      className="space-y-4"
      onSubmit={(e) => {
        e.preventDefault();
        submit();
      }}
    >
      <div role="radiogroup" className="inline-grid grid-cols-2 gap-1 rounded-control bg-paper p-1">
        {[false, true].map((p) => (
          <button
            key={String(p)}
            type="button"
            role="radio"
            aria-checked={part === p}
            onClick={() => setPart(p)}
            className={join('min-h-10 rounded-control px-4 text-sm font-semibold', part === p ? 'bg-white text-brand-strong shadow-sm' : 'text-muted')}
          >
            {p ? t('partOfDay') : t('wholeDays')}
          </button>
        ))}
      </div>
      {part ? (
        <div className="grid gap-4 sm:grid-cols-3">
          <Field label={t('dayLabel')} error={error('first_day')}>
            <Input type="date" value={first} onChange={(e) => setFirst(e.target.value)} required dir="ltr" />
          </Field>
          <Field label={t('fromTime')} error={error('from')}>
            <Input type="time" value={from} onChange={(e) => setFrom(e.target.value)} required dir="ltr" step={300} />
          </Field>
          <Field label={t('toTime')} error={error('to')}>
            <Input type="time" value={to} onChange={(e) => setTo(e.target.value)} required dir="ltr" step={300} />
          </Field>
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label={t('firstDay')} error={error('first_day')}>
            <Input type="date" value={first} onChange={(e) => setFirst(e.target.value)} required dir="ltr" />
          </Field>
          <Field label={t('lastDay')} error={error('last_day')}>
            <Input type="date" value={last} min={first || undefined} onChange={(e) => setLast(e.target.value)} dir="ltr" />
          </Field>
        </div>
      )}
      <Field label={t('reasonLabel')} error={error('reason')}>
        <Select value={reason} onChange={(e) => setReason(e.target.value)} required>
          <option value="" disabled>
            —
          </option>
          {REASONS.map((r) => (
            <option key={r} value={r}>
              {t(`reason_${r}`)}
            </option>
          ))}
        </Select>
      </Field>
      <Field label={`${t('timeOffNote')} ${common('optional')}`} error={error('note')}>
        <Textarea value={note} onChange={(e) => setNote(e.target.value)} rows={2} maxLength={500} />
      </Field>
      {state.ok ? <Notice tone="brand">{t('requestSent')}</Notice> : null}
      {state.message ? <Notice tone="danger">{state.message}</Notice> : null}
      <Button disabled={pending}>{pending ? common('loading') : t('sendRequest')}</Button>
    </form>
  );
}

export function WithdrawButton({ id }: { id: string }) {
  const t = useTranslations('teach');
  const [pending, start] = useTransition();
  return (
    <Button
      type="button"
      variant="quiet"
      disabled={pending}
      onClick={() =>
        start(async () => {
          await withdrawTimeOff(id);
        })
      }
      className="min-h-10 px-4 text-sm"
    >
      {t('withdraw')}
    </Button>
  );
}

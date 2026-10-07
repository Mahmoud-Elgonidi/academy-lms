'use client';

import { useLocale, useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { acceptShifts, commentOnShifts, saveShifts, withdrawShifts, type Result } from '@/app/[locale]/teach/actions';
import { useRouter } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Button, Field, Input, Notice, Textarea, join } from '@/components/ui';
import { hoursOf, weekOrder, weekdayName } from '@/lib/format';
import { formProblem } from '@/lib/problems';
import type { ShiftBlock, ShiftProposal } from '@/lib/teach/types';
import { minutesOf, weeklyMinutes } from './week-view';

type Row = ShiftBlock & { key: string };

let next = 0;
const keyed = (b: ShiftBlock): Row => ({ ...b, key: `b${next++}` });

/**
 * The teacher draws their week (on their own clock) and sends it, or keeps a
 * draft. Midnight as an end is 24:00. The API checks it again (shortest span,
 * overlaps, notice before cutting hours) and its words come back here.
 */
export function ShiftEditor({
  start,
  proposal,
  earliest,
  onClose,
}: {
  start: ShiftBlock[];
  proposal: ShiftProposal | null;
  earliest: { adding_time: string; removing_time: string };
  onClose?: () => void;
}) {
  const t = useTranslations('teach');
  const common = useTranslations('common');
  const locale = useLocale();
  const [rows, setRows] = useState<Row[]>(() => start.map(keyed));
  const [from, setFrom] = useState(proposal?.effective_from ?? earliest.adding_time);
  const [note, setNote] = useState(proposal?.note ?? '');
  const [min, setMin] = useState(proposal?.min_weekly_minutes ? hoursOf(proposal.min_weekly_minutes) : '');
  const [max, setMax] = useState(proposal?.max_weekly_minutes ? hoursOf(proposal.max_weekly_minutes) : '');
  const [state, setState] = useState<{ message?: string; errors?: Record<string, string[]> }>({});
  const [pending, begin] = useTransition();
  const router = useRouter();

  const valid = (r: Row) => minutesOf(r.end) > minutesOf(r.start);
  const total = weeklyMinutes(rows.filter(valid));

  const setRow = (key: string, change: Partial<ShiftBlock>) => setRows((all) => all.map((r) => (r.key === key ? { ...r, ...change } : r)));
  const add = (weekday: number) => {
    const last = rows.filter((r) => r.weekday === weekday).sort((a, b) => a.end.localeCompare(b.end)).at(-1);
    const startAt = last ? Math.min(minutesOf(last.end) + 60, 22 * 60) : 17 * 60;
    const clock = (m: number) => `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
    setRows((all) => [...all, keyed({ weekday, start: clock(startAt), end: clock(Math.min(startAt + 120, 23 * 60 + 55)) })]);
  };

  const save = (submit: boolean) => {
    if (rows.some((r) => !valid(r))) {
      setState({ message: t('endAfterStart') });
      return;
    }
    begin(async () => {
      const done: Result = await saveShifts({
        blocks: rows.map(({ weekday, start: s, end }) => ({ weekday, start: s, end })),
        effective_from: from,
        note: note.trim() || null,
        min_weekly_hours: min === '' ? null : Number(min),
        max_weekly_hours: max === '' ? null : Number(max),
        submit,
      });
      if (done.ok) {
        setState({});
        onClose?.();
        router.replace(`/teach/hours?done=${submit ? 'sent' : 'draft'}`);
      } else {
        setState({ message: formProblem({ message: done.message ?? common('problem'), errors: done.errors }, ['blocks', 'effective_from', 'note', 'min_weekly_hours', 'max_weekly_hours']), errors: done.errors });
      }
    });
  };

  const fieldError = (k: string) => state.errors?.[k]?.[0];
  const blockErrors = Object.entries(state.errors ?? {})
    .filter(([k]) => k === 'blocks' || k.startsWith('blocks.'))
    .flatMap(([, v]) => v);

  return (
    <form
      className="space-y-5"
      onSubmit={(e) => {
        e.preventDefault();
        save(true);
      }}
    >
      <div className="flex items-baseline justify-between gap-3">
        <h2 className="font-semibold">{t('editHours')}</h2>
        <span className="text-sm text-muted">{t('weeklyHours', { h: common('hoursAmount', { h: hoursOf(total) }) })}</span>
      </div>
      <ul className="divide-y divide-line rounded-control border border-line">
        {weekOrder(locale).map((day) => {
          const today = rows.filter((r) => r.weekday === day);
          return (
            <li key={day} className="flex flex-wrap items-start gap-3 p-3">
              <span className="w-20 pt-2.5 text-sm font-semibold">{weekdayName(day, locale)}</span>
              <div className="min-w-0 flex-1 space-y-2">
                {today.length === 0 ? <p className="pt-2.5 text-sm text-muted">{t('dayOff')}</p> : null}
                {today.map((r) => (
                  <div key={r.key} className="flex flex-wrap items-center gap-2">
                    <Input
                      type="time"
                      step={300}
                      value={r.start}
                      onChange={(e) => setRow(r.key, { start: e.target.value })}
                      aria-label={`${weekdayName(day, locale)} · ${t('from')}`}
                      className="w-32"
                      dir="ltr"
                    />
                    <span className="text-muted">–</span>
                    <Input
                      type="time"
                      step={300}
                      value={r.end === '24:00' ? '00:00' : r.end}
                      onChange={(e) => setRow(r.key, { end: e.target.value === '00:00' ? '24:00' : e.target.value })}
                      aria-label={`${weekdayName(day, locale)} · ${t('until')}`}
                      className={join('w-32', !valid(r) && 'border-danger')}
                      dir="ltr"
                    />
                    <button
                      type="button"
                      onClick={() => setRows((all) => all.filter((x) => x.key !== r.key))}
                      className="grid size-10 place-items-center rounded-control text-muted hover:bg-danger-soft hover:text-danger"
                      aria-label={common('remove')}
                    >
                      <Icon name="x" size={18} />
                    </button>
                  </div>
                ))}
              </div>
              <button type="button" onClick={() => add(day)} className="inline-flex min-h-10 items-center gap-1 rounded-control px-2 text-sm font-semibold text-brand-strong hover:bg-brand-soft">
                <Icon name="plus" size={16} />
                {t('addTime')}
              </button>
            </li>
          );
        })}
      </ul>
      {blockErrors.length > 0 ? <Notice tone="danger">{blockErrors[0]}</Notice> : null}

      <div className="grid gap-4 sm:grid-cols-3">
        <Field label={t('effectiveFrom')} hint={t('reduceNotice', { date: earliest.removing_time })} error={fieldError('effective_from')}>
          <Input type="date" value={from} min={earliest.adding_time} onChange={(e) => setFrom(e.target.value)} required dir="ltr" />
        </Field>
        <Field label={`${t('minHours')} ${common('optional')}`} error={fieldError('min_weekly_hours')}>
          <Input type="number" min={0} max={80} step={0.5} value={min} onChange={(e) => setMin(e.target.value)} dir="ltr" />
        </Field>
        <Field label={`${t('maxHours')} ${common('optional')}`} error={fieldError('max_weekly_hours')}>
          <Input type="number" min={0} max={80} step={0.5} value={max} onChange={(e) => setMax(e.target.value)} dir="ltr" />
        </Field>
      </div>
      <Field label={`${t('proposalNote')} ${common('optional')}`} error={fieldError('note')}>
        <Textarea value={note} onChange={(e) => setNote(e.target.value)} rows={2} maxLength={2000} />
      </Field>

      {state.message ? <Notice tone="danger">{state.message}</Notice> : null}
      <div className="flex flex-wrap gap-2">
        <Button disabled={pending}>{t('sendProposal')}</Button>
        <Button type="button" variant="quiet" disabled={pending} onClick={() => save(false)}>
          {t('saveDraft')}
        </Button>
        {onClose ? (
          <Button type="button" variant="quiet" disabled={pending} onClick={onClose}>
            {common('cancel')}
          </Button>
        ) : null}
      </div>
    </form>
  );
}

/** The proposal's own buttons: take the academy's counter-offer, edit, or withdraw. */
export function ProposalActions({ proposal, current, earliest }: { proposal: ShiftProposal; current: ShiftBlock[]; earliest: { adding_time: string; removing_time: string } }) {
  const t = useTranslations('teach');
  const common = useTranslations('common');
  const [editing, setEditing] = useState(false);
  const [message, setMessage] = useState<string>();
  const [pending, begin] = useTransition();
  const router = useRouter();

  const run = (action: () => Promise<Result>, done: 'accepted' | 'withdrawn') =>
    begin(async () => {
      const result = await action();
      if (result.ok) router.replace(`/teach/hours?done=${done}`);
      else setMessage(result.message ?? common('problem'));
    });

  if (editing) {
    return (
      <div className="mt-5 border-t border-line pt-5">
        <ShiftEditor start={proposal.week.blocks.length > 0 ? proposal.week.blocks : current} proposal={proposal} earliest={earliest} onClose={() => setEditing(false)} />
      </div>
    );
  }
  return (
    <div className="mt-5 space-y-3">
      {message ? <Notice tone="danger">{message}</Notice> : null}
      <div className="flex flex-wrap gap-2">
        {proposal.status === 'countered' ? (
          <Button disabled={pending} onClick={() => run(acceptShifts, 'accepted')}>
            {t('acceptCounter')}
          </Button>
        ) : null}
        <Button variant="quiet" disabled={pending} onClick={() => setEditing(true)}>
          {t('editProposal')}
        </Button>
        <Button
          variant="quiet"
          disabled={pending}
          onClick={() => {
            if (window.confirm(t('withdrawConfirm'))) run(withdrawShifts, 'withdrawn');
          }}
        >
          {t('withdrawProposal')}
        </Button>
      </div>
    </div>
  );
}

/** "Propose new hours": the editor opens on the approved week. */
export function ProposeButton({ current, earliest }: { current: ShiftBlock[]; earliest: { adding_time: string; removing_time: string } }) {
  const t = useTranslations('teach');
  const [open, setOpen] = useState(false);
  if (open) return <ShiftEditor start={current} proposal={null} earliest={earliest} onClose={() => setOpen(false)} />;
  return (
    <Button onClick={() => setOpen(true)}>
      <Icon name="plus" size={18} />
      {t('proposeHours')}
    </Button>
  );
}

/** Messages with the academy about this proposal. */
export function Conversation({ comments }: { comments: ShiftProposal['comments'] }) {
  const t = useTranslations('teach');
  const common = useTranslations('common');
  const [body, setBody] = useState('');
  const [error, setError] = useState<string>();
  const [pending, begin] = useTransition();
  return (
    <div>
      <ul className="space-y-2">
        {comments.map((c, i) => (
          <li key={`${c.at?.utc ?? i}-${i}`} className={join('flex', c.mine ? 'justify-start' : 'justify-end')}>
            <div className={join('max-w-[85%] rounded-card px-3.5 py-2.5', c.mine ? 'bg-brand-soft' : 'bg-paper')}>
              <p className="text-xs font-semibold text-muted">
                {c.mine ? t('you') : t('academy')}
                {c.at ? ` · ${c.at.label}` : ''}
              </p>
              <p className="whitespace-pre-line">{c.body}</p>
            </div>
          </li>
        ))}
      </ul>
      <form
        className="mt-3 flex items-end gap-2"
        onSubmit={(e) => {
          e.preventDefault();
          if (!body.trim()) return;
          begin(async () => {
            const done = await commentOnShifts(body.trim());
            if (done.ok) {
              setBody('');
              setError(undefined);
            } else {
              setError(done.message ?? common('problem'));
            }
          });
        }}
      >
        <Textarea value={body} onChange={(e) => setBody(e.target.value)} rows={1} placeholder={t('writeMessage')} aria-label={t('writeMessage')} className="min-h-11 flex-1" maxLength={2000} />
        <Button disabled={pending || !body.trim()} aria-label={common('send')} className="px-4">
          <Icon name="send" size={18} className="rtl:-scale-x-100" />
        </Button>
      </form>
      {error ? <p className="mt-2 text-sm text-danger">{error}</p> : null}
    </div>
  );
}

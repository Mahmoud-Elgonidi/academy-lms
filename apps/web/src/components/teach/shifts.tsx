'use client';

import { useLocale, useTranslations } from 'next-intl';
import { useEffect, useRef, useState, useTransition } from 'react';
import { acceptShifts, commentOnShifts, saveShifts, withdrawShifts, type Result } from '@/app/[locale]/teach/actions';
import { useRouter } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Button, Field, Input, Notice, Textarea, join } from '@/components/ui';
import { hoursOf, weekOrder, weekdayName } from '@/lib/format';
import { formProblem } from '@/lib/problems';
import { stageOf, type ShiftStage } from '@/lib/teach/shifts';
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
  from: startFrom,
  earliest,
  onClose,
}: {
  start: ShiftBlock[];
  proposal: ShiftProposal | null;
  /** The start date offered for a new proposal (default: the earliest). */
  from?: string;
  earliest: { adding_time: string; removing_time: string };
  onClose?: () => void;
}) {
  const t = useTranslations('teach');
  const common = useTranslations('common');
  const locale = useLocale();
  const [rows, setRows] = useState<Row[]>(() => start.map(keyed));
  const [from, setFrom] = useState(proposal?.effective_from ?? startFrom ?? earliest.adding_time);
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


/** The step it is on: 0 draft, 2 in review, 3 the academy's answer. */
const stepOf = (stage: ShiftStage) => (stage === 'draft' ? 0 : stage === 'review' ? 2 : 3);

/** Four steps: written, sent, in review, and the academy's answer (counter-offer, changes asked, approved or rejected). */
export function ShiftStepper({ stage }: { stage: ShiftStage }) {
  const t = useTranslations('teach');
  const step = stepOf(stage);
  const answer = { countered: t('stepCountered'), changes: t('stepChanges'), approved: t('stepApproved'), rejected: t('stepRejected') } as Partial<Record<ShiftStage, string>>;
  const labels = [t('stepDraft'), t('stepSent'), t('stepReview'), answer[stage] ?? t('stepDecision')];
  const end = stage === 'rejected' ? 'danger' : stage === 'countered' || stage === 'changes' ? 'attention' : 'brand';
  return (
    <ol className="grid grid-cols-4" aria-label={t('proposalSteps')}>
      {labels.map((label, i) => {
        const done = i < step || stage === 'approved';
        const answered = i === 3 && step === 3;
        const now = i === step;
        return (
          <li key={i} className="flex flex-col items-center gap-1.5 text-center" aria-current={now ? 'step' : undefined}>
            <span className="flex w-full items-center">
              <span className={join('h-0.5 flex-1', i === 0 ? 'bg-transparent' : i <= step ? 'bg-brand' : 'bg-line')} />
              <span
                className={join(
                  'grid size-7 shrink-0 place-items-center rounded-full border-2 text-xs font-bold',
                  answered && end === 'danger' && 'border-danger bg-danger text-white',
                  answered && end === 'attention' && 'border-attention bg-attention text-white',
                  (answered ? end === 'brand' : done) && 'border-brand bg-brand text-white',
                  !answered && !done && now && 'border-brand bg-white text-brand-strong',
                  !answered && !done && !now && 'border-line bg-white text-muted',
                )}
              >
                {answered && stage === 'rejected' ? <Icon name="x" size={14} /> : answered && stage !== 'approved' ? '!' : done ? <Icon name="check" size={14} /> : i + 1}
              </span>
              <span className={join('h-0.5 flex-1', i === 3 ? 'bg-transparent' : i < step ? 'bg-brand' : 'bg-line')} />
            </span>
            <span className={join('text-xs', now ? 'font-bold text-ink' : 'text-muted')}>{label}</span>
          </li>
        );
      })}
    </ol>
  );
}

/**
 * The one thing to do at each step: send a draft, withdraw while the academy
 * reviews, take its counter-offer, or edit and send again when it asks for
 * changes; editing and withdrawing stay small links beside an answer.
 */
export function ProposalActions({ proposal, start, earliest }: { proposal: ShiftProposal; start: ShiftBlock[]; earliest: { adding_time: string; removing_time: string } }) {
  const t = useTranslations('teach');
  const common = useTranslations('common');
  const [editing, setEditing] = useState(false);
  const [message, setMessage] = useState<string>();
  const [pending, begin] = useTransition();
  const router = useRouter();
  const stage = stageOf(proposal.status);

  const run = (action: () => Promise<Result>, done: 'accepted' | 'withdrawn') =>
    begin(async () => {
      const result = await action();
      if (result.ok) router.replace(`/teach/hours?done=${done}`);
      else setMessage(result.message ?? common('problem'));
    });
  const withdraw = () => {
    if (window.confirm(t('withdrawConfirm'))) run(withdrawShifts, 'withdrawn');
  };
  const link = 'inline-flex min-h-10 items-center rounded-control px-3 text-sm font-semibold hover:bg-paper disabled:opacity-50';

  if (editing) {
    return (
      <div className="mt-5 border-t border-line pt-5">
        <ShiftEditor start={proposal.week.blocks.length > 0 ? proposal.week.blocks : start} proposal={proposal} earliest={earliest} onClose={() => setEditing(false)} />
      </div>
    );
  }
  return (
    <div className="mt-5 space-y-3">
      {message ? <Notice tone="danger">{message}</Notice> : null}
      <div className="flex flex-wrap items-center gap-2">
        {stage === 'countered' ? (
          <Button disabled={pending} onClick={() => run(acceptShifts, 'accepted')}>
            <Icon name="check" size={18} />
            {t('acceptCounter')}
          </Button>
        ) : stage === 'review' ? (
          <Button variant="quiet" className="text-danger" disabled={pending} onClick={withdraw}>
            {t('withdrawProposal')}
          </Button>
        ) : (
          <Button disabled={pending} onClick={() => setEditing(true)}>
            {t('editAndSend')}
          </Button>
        )}
        {stage === 'countered' ? (
          <button type="button" className={join(link, 'text-brand-strong')} disabled={pending} onClick={() => setEditing(true)}>
            {t('editAndSend')}
          </button>
        ) : null}
        {stage === 'countered' || stage === 'changes' ? (
          <button type="button" className={join(link, 'text-danger')} disabled={pending} onClick={withdraw}>
            {t('withdrawProposal')}
          </button>
        ) : null}
      </div>
    </div>
  );
}

/** "Propose new hours": the editor opens on the latest agreed week. */
export function ProposeButton({ start, from, earliest }: { start: ShiftBlock[]; from?: string; earliest: { adding_time: string; removing_time: string } }) {
  const t = useTranslations('teach');
  const [open, setOpen] = useState(false);
  if (open) return <ShiftEditor start={start} proposal={null} from={from} earliest={earliest} onClose={() => setOpen(false)} />;
  return (
    <Button onClick={() => setOpen(true)}>
      <Icon name="plus" size={18} />
      {t('proposeHours')}
    </Button>
  );
}

type Comment = ShiftProposal['comments'][number];

/** The messages in time order, with "sent" placed where it happened. */
function lines(comments: Comment[], sent: ShiftProposal['submitted_at']): ({ kind: 'event'; text: string; at?: string } | { kind: 'message'; c: Comment })[] {
  const out: ReturnType<typeof lines> = [];
  let placed = !sent;
  for (const c of comments) {
    if (!placed && sent && c.at && c.at.utc > sent.utc) {
      out.push({ kind: 'event', text: 'sent', at: sent.label });
      placed = true;
    }
    out.push(c.author_role === 'system' ? { kind: 'event', text: c.body, at: c.at?.label } : { kind: 'message', c });
  }
  if (!placed && sent) out.push({ kind: 'event', text: 'sent', at: sent.label });
  return out;
}

/**
 * The proposal's conversation, one row on the page: how many messages and the
 * last one; it opens beside the page with the teacher's words on their side,
 * the academy's on the other, and the proposal's steps as small lines.
 */
export function ConversationPanel({ proposal }: { proposal: ShiftProposal }) {
  const t = useTranslations('teach');
  const common = useTranslations('common');
  const [open, setOpen] = useState(false);
  const [error, setError] = useState<string>();
  const [pending, begin] = useTransition();
  const ref = useRef<HTMLDialogElement>(null);
  const list = useRef<HTMLOListElement>(null);
  const form = useRef<HTMLFormElement>(null);
  const said = proposal.comments.filter((c) => c.author_role !== 'system');
  const last = said.at(-1);
  const all = lines(proposal.comments, proposal.submitted_at);

  useEffect(() => {
    const d = ref.current;
    if (!d) return;
    if (open && !d.open) d.showModal();
    if (!open && d.open) d.close();
  }, [open]);
  useEffect(() => {
    if (open && list.current) list.current.scrollTop = list.current.scrollHeight;
  }, [open, proposal.comments.length]);

  const write = (data: FormData) => {
    const body = String(data.get('body') ?? '').trim();
    if (!body) return;
    begin(async () => {
      const done = await commentOnShifts(body);
      if (done.ok) {
        form.current?.reset();
        setError(undefined);
      } else {
        setError(done.message ?? common('problem'));
      }
    });
  };

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        className="lms-lift flex w-full items-center gap-3 rounded-card border border-line bg-white px-4 py-3 text-start hover:border-brand-line"
        aria-haspopup="dialog"
      >
        <span className="relative grid size-10 shrink-0 place-items-center rounded-full bg-brand-soft text-brand-strong">
          <Icon name="chat" size={20} />
          {last && !last.mine ? <span className="absolute -end-0.5 -top-0.5 size-2.5 rounded-full bg-attention" /> : null}
        </span>
        <span className="min-w-0 flex-1">
          <span className="block font-semibold">{t('conversationCount', { n: said.length })}</span>
          <span className="block truncate text-sm text-muted">{last ? `${last.mine ? t('you') : t('academy')}: ${last.body}` : t('conversationStart')}</span>
        </span>
        <Icon name="chevron" size={18} className="shrink-0 text-muted rtl:-scale-x-100" />
      </button>

      <dialog
        ref={ref}
        onClose={() => setOpen(false)}
        aria-label={t('conversation')}
        className="ms-auto me-0 my-0 h-dvh max-h-dvh w-[min(28rem,100vw)] max-w-none border-s border-line bg-white p-0 text-ink backdrop:bg-black/30"
      >
        <div className="flex h-full flex-col">
          <header className="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
            <div className="min-w-0">
              <h2 className="font-semibold">{t('conversation')}</h2>
              <p className="truncate text-xs text-muted">{proposal.status_label}</p>
            </div>
            <button type="button" onClick={() => setOpen(false)} className="grid size-9 place-items-center rounded-control text-muted hover:bg-paper" aria-label={common('close')}>
              <Icon name="x" size={18} />
            </button>
          </header>
          <ol ref={list} className="flex-1 space-y-3 overflow-y-auto px-4 py-4">
            {said.length === 0 ? <li className="py-8 text-center text-sm text-muted">{t('conversationEmpty')}</li> : null}
            {all.map((line, i) =>
              line.kind === 'event' ? (
                <li key={i} className="flex items-center gap-3 text-xs text-muted">
                  <span className="h-px flex-1 bg-line" />
                  <span className="max-w-[80%] text-center">
                    {line.text === 'sent' ? t('proposalSentEvent') : line.text}
                    {line.at ? ` · ${line.at}` : ''}
                  </span>
                  <span className="h-px flex-1 bg-line" />
                </li>
              ) : (
                <li key={i} className={join('flex flex-col gap-1', line.c.mine ? 'items-end' : 'items-start')}>
                  <div className={join('max-w-[85%] whitespace-pre-line break-words rounded-card px-4 py-2.5 text-[15px] leading-7', line.c.mine ? 'bg-brand text-white' : 'bg-paper')}>{line.c.body}</div>
                  <span className="text-xs text-muted">
                    {line.c.mine ? t('you') : t('academy')}
                    {line.c.at ? ` · ${line.c.at.label}` : ''}
                  </span>
                </li>
              ),
            )}
          </ol>
          {error ? (
            <div className="px-4 pb-2">
              <Notice tone="danger">{error}</Notice>
            </div>
          ) : null}
          <form ref={form} action={write} className="flex items-end gap-2 border-t border-line p-3">
            <textarea
              name="body"
              rows={1}
              required
              maxLength={2000}
              aria-label={t('writeMessage')}
              placeholder={t('writeMessage')}
              className="min-h-11 flex-1 resize-none rounded-[1.4rem] border border-line bg-white px-4 py-2.5 text-[15px] [field-sizing:content] focus:outline-2 focus:outline-brand"
              onKeyDown={(e) => {
                if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) {
                  e.preventDefault();
                  form.current?.requestSubmit();
                }
              }}
            />
            <Button type="submit" disabled={pending} aria-label={common('send')} className="px-4">
              <Icon name="send" size={18} className="rtl:-scale-x-100" />
            </Button>
          </form>
        </div>
      </dialog>
    </>
  );
}

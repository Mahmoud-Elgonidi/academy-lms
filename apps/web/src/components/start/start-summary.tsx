'use client';

import { useTranslations } from 'next-intl';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Icon } from '@/components/icons';
import { FormatBadge } from '@/components/lesson-format';
import { Button } from '@/components/ui';
import { priceOf, type Step } from '@/lib/start/flow';
import type { SkippedWeek, StartLearner, StartOffer, StartPlan } from '@/lib/start/types';
import type { LocalTime } from '@/lib/time';

/** What the family chose so far, as the summary shows it. */
export type SummaryFacts = {
  learner: StartLearner | null;
  subject: string | null;
  plan: StartPlan | null;
  teacher: string | null;
  times: string[];
  first: LocalTime | null;
  /** The weeks the chosen times start without (a holiday, the teacher's leave), in date order. */
  skips: SkippedWeek[];
  offer: StartOffer | null;
};

type Primary = { label: string; disabled: boolean; onClick: () => void } | null;

/**
 * The family's choices so far and what the first invoice comes to: beside
 * the steps on a wide screen (sticky), and on a phone a bar over the tab bar
 * with the total and the step's button that opens the same summary as a
 * sheet from the bottom.
 */
export function Summary({ facts, primary, step, isDone }: { facts: SummaryFacts; primary: Primary; step: Step; isDone: (step: Step) => boolean }) {
  const t = useTranslations('start.summary');
  const [open, setOpen] = useState(false);
  const opener = useRef<HTMLButtonElement>(null);
  const sheet = useRef<HTMLDivElement>(null);
  const price = facts.plan ? priceOf(facts.plan) : null;

  useEffect(() => {
    if (!open) return;
    sheet.current?.focus();
    const close = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('keydown', close);
    return () => document.removeEventListener('keydown', close);
  }, [open]);
  const hide = () => {
    setOpen(false);
    opener.current?.focus();
  };

  return (
    <>
      <aside className="st-aside hidden lg:block" aria-labelledby="st-summary">
        <div className="rounded-card border border-line bg-white p-5">
          <h2 id="st-summary" className="text-sm font-semibold text-muted">{t('title')}</h2>
          <Facts facts={facts} isDone={isDone} />
          <Total price={price} plan={facts.plan} />
          {primary && step === 'review' ? <Button wide className="mt-4" onClick={primary.onClick} disabled={primary.disabled}>{primary.label}</Button> : null}
          <p className="mt-3 flex items-start gap-1.5 text-xs text-muted"><Icon name="shield" size={14} className="mt-0.5 shrink-0" />{t('noCharge')}</p>
        </div>
      </aside>

      <div className="st-dock lg:hidden" data-hides-zone-chip>
        <div className="flex items-center gap-3">
          <button ref={opener} type="button" onClick={() => setOpen(true)} aria-expanded={open} aria-controls="st-sheet" className="shrink-0 text-start">
            <span className="block text-xs text-muted">{t('open')}</span>
            <span className="flex items-baseline gap-2">
              {price?.was ? <s className="st-was hidden text-xs min-[420px]:inline">{price.was}</s> : null}
              <b className="whitespace-nowrap font-heading text-lg">{price ? price.total : t('noPlan')}</b>
              <Icon name="chevron" size={16} className="-rotate-90 text-muted" />
            </span>
          </button>
          {primary ? <Button onClick={primary.onClick} disabled={primary.disabled} className="min-w-0 flex-1 px-3 leading-tight">{primary.label}</Button> : null}
        </div>
      </div>

      {open ? (
        <>
          <div className="st-sheet-back lg:hidden" onClick={hide} aria-hidden="true" />
          <div id="st-sheet" ref={sheet} role="dialog" aria-modal="true" aria-labelledby="st-sheet-title" tabIndex={-1} className="st-sheet outline-none lg:hidden">
            <div className="st-sheet-grip" aria-hidden="true" />
            <div className="flex items-center justify-between gap-2">
              <h2 id="st-sheet-title" className="font-bold">{t('title')}</h2>
              <button type="button" onClick={hide} className="grid size-10 place-items-center rounded-full hover:bg-paper" aria-label={t('close')}><Icon name="x" /></button>
            </div>
            <Facts facts={facts} isDone={isDone} />
            <Total price={price} plan={facts.plan} />
            <p className="mt-3 flex items-start gap-1.5 text-xs text-muted"><Icon name="shield" size={14} className="mt-0.5 shrink-0" />{t('noCharge')}</p>
          </div>
        </>
      ) : null}
    </>
  );
}

function Facts({ facts, isDone }: { facts: SummaryFacts; isDone: (step: Step) => boolean }) {
  const t = useTranslations('start.summary');
  const s = useTranslations('start.shape');
  const plan = facts.plan;
  const row = (label: string, value: ReactNode, done: boolean, icon: 'user' | 'book' | 'calendar' | 'people' | 'clock' | 'star') => (
    <div className="flex gap-2.5 py-2.5">
      <span className={done ? 'text-brand' : 'text-line'} aria-hidden="true"><Icon name={done ? 'check' : icon === 'user' ? 'people' : icon} size={18} /></span>
      <div className="min-w-0">
        <dt className="text-xs text-muted">{label}</dt>
        <dd className={done ? 'font-semibold' : 'text-sm text-muted'}>{done ? value : t('notYet')}</dd>
      </div>
    </div>
  );
  return (
    <dl className="mt-2 divide-y divide-line">
      {row(t('learner'), facts.learner ? <bdi>{facts.learner.is_self ? t('me', { name: facts.learner.name }) : facts.learner.name}</bdi> : null, Boolean(facts.learner), 'user')}
      {row(t('subject'), facts.subject, Boolean(facts.subject), 'book')}
      {row(t('plan'), plan ? (
        <span className="flex flex-wrap items-center gap-1.5">
          {s('perWeek', { count: plan.sessions_per_week })} · {s('minutes', { minutes: plan.session_minutes })}
          {plan.format === 'group' ? <FormatBadge format="group" /> : null}
        </span>
      ) : null, Boolean(plan), 'calendar')}
      {row(plan?.format === 'group' ? t('group') : t('teacher'), facts.teacher ? <bdi>{facts.teacher}</bdi> : null, isDone('teacher'), 'people')}
      {plan?.format === 'group' ? null : row(t('times'), (
        <ul className="space-y-0.5">{facts.times.map((x) => <li key={x}>{x}</li>)}</ul>
      ), isDone('times'), 'clock')}
      {facts.first ? row(t('first'), facts.first.label, true, 'star') : null}
      {facts.skips.length > 0 && isDone('times') ? row(t('skipped'), (
        <ul className="space-y-0.5 text-sm font-normal" data-summary-skips>{facts.skips.map((x) => <li key={x.text}>{x.text}</li>)}</ul>
      ), true, 'calendar') : null}
    </dl>
  );
}

function Total({ price, plan }: { price: ReturnType<typeof priceOf> | null; plan: StartPlan | null }) {
  const t = useTranslations('start.summary');
  if (!price || !plan) return null;
  return (
    <div className="mt-3 rounded-control bg-paper p-3" data-summary-total>
      <p className="text-xs text-muted">{t('firstInvoice')}</p>
      <p className="flex flex-wrap items-baseline gap-2">
        {price.was ? <s className="st-was text-sm">{price.was}</s> : null}
        <b className="font-heading text-2xl">{price.total}</b>
      </p>
      {price.discounts.map((d) => <p key={d.label} className="text-xs font-semibold text-brand-strong">{d.label}</p>)}
      <p className="mt-1 text-xs text-muted">{t('perLesson', { price: plan.per_lesson.amount })}</p>
    </div>
  );
}

'use client';

import { useTranslations } from 'next-intl';
import type { ReactNode } from 'react';
import { Icon } from '@/components/icons';
import { FormatBadge, SeatDots } from '@/components/lesson-format';
import { useClock } from '@/components/time-format';
import { Badge, Notice, buttonLook, join } from '@/components/ui';
import { Link } from '@/i18n/navigation';
import { dayLabel } from '@/lib/format';
import { initialOf } from '@/lib/site/initial';
import { plansByFormat, priceOf, skipsOf, teachersFor, timesReady, weekdayOf } from '@/lib/start/flow';
import type { StartGroup, StartLearner, StartOffer, StartOptions, StartPlan, StartSlots, StartSubject, StartTeacher } from '@/lib/start/types';
import { CancelStart } from './invoice-actions';

/*
 * The start flow's steps (docs/architecture/02, section 27, J3): each a list
 * of cards to choose from as a radio group (one press chooses and goes on),
 * and the weekly times as toggles, a plan's lessons a week, one a day.
 */

/** One card of a radio group: the whole card presses; a card that cannot be chosen says why inside. */
function Choice({ checked, disabled, onChoose, format, children, label, data }: {
  checked: boolean;
  disabled?: boolean;
  onChoose: () => void;
  format?: 'private' | 'group';
  children: ReactNode;
  label: string;
  data?: Record<string, string>;
}) {
  return (
    <div
      role="radio"
      aria-checked={checked}
      aria-disabled={disabled || undefined}
      aria-label={label}
      tabIndex={disabled ? -1 : 0}
      data-format={format}
      className={join('st-choice', !disabled && 'lift cursor-pointer')}
      onClick={disabled ? undefined : onChoose}
      onKeyDown={(e) => {
        if (disabled) return;
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onChoose();
        }
      }}
      {...data}
    >
      {disabled ? null : <span className="st-tick" aria-hidden="true"><Icon name="check" size={14} /></span>}
      {children}
    </div>
  );
}

export function LearnerStep({ learners, chosen, onChoose }: { learners: StartLearner[]; chosen?: string; onChoose: (id: string) => void }) {
  const t = useTranslations('start.learner');
  return (
    <div role="radiogroup" aria-labelledby="st-title" className="stagger grid gap-3 sm:grid-cols-2">
      {learners.map((l) => (
        <Choice key={l.id} checked={chosen === l.id} onChoose={() => onChoose(l.id)} label={l.is_self ? t('me', { name: l.name }) : l.name} data={{ 'data-learner': l.id }}>
          <span className="flex items-center gap-3 pe-8">
            <span className="st-avatar" aria-hidden="true">{initialOf(l.name)}</span>
            <span className="min-w-0">
              <span className="block text-lg font-bold">{l.is_self ? t('me', { name: l.name }) : l.name}</span>
              <span className="block text-sm text-muted">{l.is_self ? t('forMe') : t('forChild')}</span>
              {l.consent_waiting ? <span className="mt-1.5 inline-flex"><Badge tone="attention">{t('consentWaiting')}</Badge></span> : null}
            </span>
          </span>
          {l.consent_waiting ? <span className="mt-2 block text-xs text-muted">{t('consentLater')}</span> : null}
        </Choice>
      ))}
    </div>
  );
}

const STATUS_TONE: Record<string, 'attention' | 'brand' | 'info'> = { pending_payment: 'attention', active: 'brand', paused: 'info' };

export function SubjectStep({ subjects, chosen, learner, onChoose }: { subjects: StartSubject[]; chosen?: string; learner: string; onChoose: (id: string) => void }) {
  const t = useTranslations('start.subject');
  if (subjects.length === 0) return <Notice tone="muted">{t('none')}</Notice>;
  const open = subjects.filter((s) => s.can_start);
  const taken = subjects.filter((s) => !s.can_start);
  return (
    <div className="space-y-5">
      <div role="radiogroup" aria-labelledby="st-title" className="stagger grid gap-3 sm:grid-cols-2">
        {open.map((s) => (
          <Choice key={s.id} checked={chosen === s.id} onChoose={() => onChoose(s.id)} label={s.name} data={{ 'data-subject': s.id }}>
            <span className="flex items-center gap-3 pe-8">
              <span className="grid size-11 shrink-0 place-items-center rounded-control bg-brand-soft text-brand-strong" aria-hidden="true"><Icon name="book" /></span>
              <span className="text-lg font-bold">{s.name}</span>
            </span>
          </Choice>
        ))}
      </div>
      {taken.length > 0 ? (
        <section aria-labelledby="st-taken">
          <h3 id="st-taken" className="mb-2 text-sm font-semibold text-muted">{t('already', { learner })}</h3>
          <ul className="grid gap-3">
            {taken.map((s) => (
              <li key={s.id} className="rounded-card border border-line bg-paper p-4" data-subject-taken={s.id}>
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="font-bold">{s.name}</p>
                  {s.course ? <Badge tone={STATUS_TONE[s.course.status] ?? 'muted'}>{t(`status.${s.course.status === 'pending_payment' || s.course.status === 'paused' ? s.course.status : 'active'}`)}</Badge> : null}
                </div>
                {s.why_not ? <p className="mt-1.5 text-sm text-ink/80">{s.why_not}</p> : null}
                <div className="mt-3 flex flex-wrap gap-2">
                  {s.course?.status === 'pending_payment' ? (
                    <>
                      <Link href="/family/money" className={join(buttonLook('primary'), 'min-h-10 px-4 text-sm')}>{t('payIt')}</Link>
                      <CancelStart enrollment={s.course.enrollment_id} compact />
                    </>
                  ) : (
                    <Link href="/family/lessons" className={join(buttonLook('quiet'), 'min-h-10 px-4 text-sm')}>{t('openCourse')}</Link>
                  )}
                </div>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </div>
  );
}

/** A plan in plain words beyond its title: the block and how long it lasts. */
function Shape({ plan }: { plan: StartPlan }) {
  const t = useTranslations('start.shape');
  return (
    <p className="mt-1.5 flex items-start gap-2 text-sm text-ink/85">
      <Icon name="book" size={16} className="mt-0.5 shrink-0 text-muted" />
      <span>{t('block', { count: plan.sessions_per_block })} · {t('validity', { days: plan.validity_days })}</span>
    </p>
  );
}

export function PlanStep({ plans, chosen, recommended, offer, region, onChoose }: {
  plans: StartPlan[];
  chosen?: string;
  recommended: string | null;
  offer: StartOffer | null;
  region: StartOptions['region'];
  onChoose: (id: string) => void;
}) {
  const t = useTranslations('start.plan');
  const s = useTranslations('start.shape');
  const kinds = plansByFormat(plans);
  if (plans.length === 0) return <Notice tone="muted">{t('none')}</Notice>;

  const card = (plan: StartPlan) => {
    const price = priceOf(plan);
    const group = plan.format === 'group';
    return (
      <Choice key={plan.id} checked={chosen === plan.id} onChoose={() => onChoose(plan.id)} format={plan.format} label={`${s('perWeek', { count: plan.sessions_per_week })} · ${s('minutes', { minutes: plan.session_minutes })} · ${price.total}`} data={{ 'data-plan': plan.id }}>
        <div className="flex flex-wrap items-center gap-2 pe-8">
          <FormatBadge format={plan.format} size="md" />
          {plan.id === recommended ? <Badge tone="brand"><Icon name="star" size={13} className="me-1" />{t('recommended')}</Badge> : null}
          {plan.shared ? <Badge tone="info">{t('shared')}</Badge> : null}
          {price.offer ? <Badge tone="attention">{t('offerBadge', { percent: offer?.percent ?? 0 })}</Badge> : null}
        </div>
        <p className="mt-2 text-lg font-bold">{s('perWeek', { count: plan.sessions_per_week })} · {s('minutes', { minutes: plan.session_minutes })}</p>
        <Shape plan={plan} />
        <div className="mt-3 flex flex-wrap items-end justify-between gap-2 border-t border-line pt-3">
          <div className="st-price">
            {price.was ? <span className="st-was me-2 text-sm">{price.was}</span> : null}
            <b className={group ? 'text-group-strong' : undefined}>{price.total}</b>
            <span className="block text-xs text-muted">{price.was ? t('firstInvoice') : t('perBlock')}</span>
          </div>
          <p className="text-sm font-semibold text-muted">{t('perLesson', { price: plan.per_lesson.amount })}</p>
        </div>
        {price.discounts.length > 0 && !price.offer ? <p className="mt-2 text-xs text-brand-strong">{price.discounts.map((d) => d.label).join(' · ')}</p> : null}
        <p className="mt-2 text-xs text-muted">{t('renews', { count: plan.renews_at_lessons_left })}</p>
        {plan.shared ? <p className="mt-1 text-xs text-muted">{t('sharedHint')}</p> : null}
      </Choice>
    );
  };

  return (
    <div className="space-y-6">
      {offer?.state === 'open' ? (
        <div className="flex items-start gap-3 rounded-card border border-attention/40 bg-attention-soft p-4" data-offer="open">
          <Icon name="spark" className="mt-0.5 shrink-0 text-attention" />
          <p className="text-[15px]"><b>{t('offerTitle', { percent: offer.percent })}</b> {offer.ends_at ? t('offerUntil', { until: offer.ends_at.label }) : null}</p>
        </div>
      ) : null}
      {offer?.state === 'ended' ? <Notice tone="muted">{t('offerEnded')}</Notice> : null}
      {kinds.private.length > 0 ? (
        <section aria-labelledby="st-private">
          <h3 id="st-private" className="mb-1 font-bold">{t('privateTitle')}</h3>
          <p className="mb-3 text-sm text-muted">{t('privateHint')}</p>
          <div role="radiogroup" aria-labelledby="st-private" className="stagger grid gap-3 sm:grid-cols-2">{kinds.private.map(card)}</div>
        </section>
      ) : null}
      {kinds.group.length > 0 ? (
        <section aria-labelledby="st-group">
          <h3 id="st-group" className="mb-1 flex items-center gap-2 font-bold text-group-strong"><Icon name="people" size={18} />{t('groupTitle')}</h3>
          <p className="mb-3 text-sm text-muted">{t('groupHint')}</p>
          <div role="radiogroup" aria-labelledby="st-group" className="stagger grid gap-3 sm:grid-cols-2">{kinds.group.map(card)}</div>
        </section>
      ) : null}
      {region ? <p className="text-xs text-muted">{t('region', { region: region.name, currency: region.currency })}</p> : null}
    </div>
  );
}

export function TeacherStep({ teachers, chosen, learner, trial, onChoose }: {
  teachers: StartTeacher[];
  chosen?: string;
  learner: StartLearner | undefined;
  trial: StartOptions['trial'];
  onChoose: (id: string) => void;
}) {
  const t = useTranslations('start.teacher');
  const list = teachersFor(teachers);
  return (
    <div className="space-y-4">
      {trial && !trial.teacher_available ? <Notice tone="info">{t('trialGone')}</Notice> : null}
      {list.length === 0 ? (
        <Notice tone="muted">{t('none')}</Notice>
      ) : (
        <div role="radiogroup" aria-labelledby="st-title" className="stagger grid gap-3">
          {list.map((x) => (
            <Choice key={x.id} checked={chosen === x.id} onChoose={() => onChoose(x.id)} label={x.name} data={{ 'data-teacher': x.id }}>
              <span className="flex items-start gap-3 pe-8">
                {x.photo_url ? (
                  // eslint-disable-next-line @next/next/no-img-element -- the teacher's own photo from their card on the academy's page
                  <img src={x.photo_url} alt="" className="st-avatar" />
                ) : (
                  <span className="st-avatar" aria-hidden="true">{initialOf(x.name)}</span>
                )}
                <span className="min-w-0">
                  <span className="block text-lg font-bold"><bdi>{x.name}</bdi></span>
                  {x.trial_teacher ? (
                    <span className="mt-1 inline-flex"><Badge tone="brand"><Icon name="star" size={13} className="me-1" />{learner?.is_self ? t('yourTrialTeacher') : t('trialTeacher', { learner: learner?.name ?? '' })}</Badge></span>
                  ) : null}
                  {x.bio ? <span className="mt-1.5 block text-sm leading-6 text-ink/80">{x.bio}</span> : null}
                </span>
              </span>
            </Choice>
          ))}
        </div>
      )}
    </div>
  );
}

export function GroupStep({ groups, problem, chosen, onChoose, onPrivate, help }: {
  groups: StartGroup[];
  problem: string | null;
  chosen?: string;
  onChoose: (id: string) => void;
  onPrivate: () => void;
  help: string;
}) {
  const t = useTranslations('start.group');
  if (problem || groups.length === 0) {
    return (
      <div className="rounded-card border border-group-line bg-group-soft p-5" data-empty="groups">
        <p className="flex items-start gap-2 font-semibold"><Icon name="people" className="mt-0.5 shrink-0 text-group-strong" />{problem ?? t('none')}</p>
        <div className="mt-3 flex flex-wrap gap-2">
          <button type="button" onClick={onPrivate} className={join(buttonLook('primary'), 'min-h-10 px-4 text-sm')}>{t('private')}</button>
          <Link href={help} className={join(buttonLook('quiet'), 'min-h-10 px-4 text-sm')}>{t('ask')}</Link>
        </div>
      </div>
    );
  }
  return (
    <div role="radiogroup" aria-labelledby="st-title" className="stagger grid gap-3">
      {groups.map((g) => (
        <Choice key={g.id} checked={chosen === g.id} onChoose={() => onChoose(g.id)} format="group" label={`${g.name} · ${g.teacher.name}`} data={{ 'data-group': g.id }}>
          <div className="flex flex-wrap items-center gap-2 pe-8">
            <FormatBadge format="group" seats={{ taken: g.seats.taken, capacity: g.seats.capacity }} />
            <Badge tone={g.status === 'forming' ? 'attention' : 'brand'}>{g.status === 'forming' ? t('forming') : t('running')}</Badge>
          </div>
          <p className="mt-2 text-lg font-bold">{g.name}</p>
          <p className="text-sm text-muted"><bdi>{g.teacher.name}</bdi></p>
          <ul className="mt-2 flex flex-wrap gap-1.5">
            {g.times.map((x) => <li key={`${x.weekday}${x.start}`} className="rounded-full bg-white px-2.5 py-0.5 text-sm font-semibold text-group-strong ring-1 ring-group-line">{x.label}</li>)}
          </ul>
          <p className="mt-2 flex items-center gap-2 text-sm">
            <SeatDots taken={g.seats.taken} capacity={g.seats.capacity} />
            <span>{t('seats', { free: g.seats.free, capacity: g.seats.capacity })}</span>
          </p>
          {g.status === 'forming' && g.min_to_start ? <p className="mt-1 text-sm text-ink/80">{t('startsAt', { count: g.min_to_start, taken: g.seats.taken })}</p> : null}
          {g.status !== 'forming' && g.next ? <p className="mt-1 text-sm text-ink/80">{t('next', { when: g.next.label })}</p> : null}
          {g.held > 0 ? <p className="mt-1 text-xs text-muted">{t('held', { count: g.held })}</p> : null}
        </Choice>
      ))}
    </div>
  );
}

export function TimesStep({ slots, problem, plan, picks, full, zoneLabel, teacher, locale, onPress, onTeacher, onPlan }: {
  slots: StartSlots | null;
  problem: string | null;
  plan: StartPlan;
  picks: string[];
  full: boolean;
  zoneLabel: string;
  teacher: string;
  locale: string;
  onPress: (key: string) => void;
  onTeacher: () => void;
  onPlan: () => void;
}) {
  const t = useTranslations('start.times');
  const { wall } = useClock();
  const perWeek = slots?.per_week ?? plan.sessions_per_week;
  const suggested = new Set(slots?.suggested ?? []);
  const days = slots?.days ?? [];
  const fewDays = days.length < perWeek;

  if (problem || !slots || fewDays) {
    return (
      <div className="rounded-card border border-attention/40 bg-attention-soft p-5" data-empty="times">
        <p className="flex items-start gap-2 font-semibold"><Icon name="calendar" className="mt-0.5 shrink-0 text-attention" />
          {problem ?? (days.length === 0 ? t('none', { teacher }) : t('fewDays', { teacher, count: perWeek }))}
        </p>
        <div className="mt-3 flex flex-wrap gap-2">
          <button type="button" onClick={onTeacher} className={join(buttonLook('primary'), 'min-h-10 px-4 text-sm')}>{t('otherTeacher')}</button>
          {perWeek > 1 ? <button type="button" onClick={onPlan} className={join(buttonLook('quiet'), 'min-h-10 px-4 text-sm')}>{t('fewerLessons')}</button> : null}
        </div>
      </div>
    );
  }

  const pickedDays = new Set(picks.map(weekdayOf));
  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-white px-4 py-3">
        <p className="flex items-center gap-2 text-sm font-semibold">
          <span className="st-count" aria-hidden="true">{Array.from({ length: perWeek }, (_, i) => <i key={i} data-on={i < picks.length} />)}</span>
          <span aria-live="polite">{t('chosen', { count: picks.length, of: perWeek })}</span>
        </p>
        <p className="flex items-center gap-1.5 text-xs text-muted"><Icon name="globe" size={14} />{t('zone', { zone: zoneLabel })}</p>
      </div>
      {slots.first_day ? <p className="text-sm text-muted">{t('firstDay', { day: dayLabel(slots.first_day.local.slice(0, 10), locale) })}</p> : null}
      {suggested.size > 0 ? <p className="flex items-center gap-2 text-sm text-ink/80"><span className="inline-block h-4 w-6 rounded border-[1.5px] border-dashed border-brand-line" aria-hidden="true" />{t('suggested')}</p> : null}
      {full ? <Notice tone="attention">{t('full', { count: perWeek })}</Notice> : null}

      <div className="grid gap-3">
        {days.map((day) => {
          const on = pickedDays.has(day.weekday);
          // The weeks this day's times start without, each sentence once, under the times; a time with one carries a marker and is described by it.
          const skips = new Map<string, { id: string; text: string; times: string[] }>();
          for (const time of day.times) {
            for (const skip of skipsOf(time)) {
              const found = skips.get(skip.text) ?? { id: `st-skip-${day.weekday}-${skips.size}`, text: skip.text, times: [] };
              found.times.push(time.key);
              skips.set(skip.text, found);
            }
          }
          const notes = [...skips.values()];
          const describedBy = (key: string) => notes.filter((n) => n.times.includes(key)).map((n) => n.id).join(' ') || undefined;
          return (
            <section key={day.weekday} className="st-day rounded-card border border-line bg-white p-4" data-picked={on} aria-labelledby={`st-day-${day.weekday}`}>
              <h3 id={`st-day-${day.weekday}`} className="st-day-name mb-2.5 flex items-center gap-2 font-bold">
                {on ? <Icon name="check" size={18} className="text-brand" /> : <span className="inline-block size-[18px] rounded-full border-[1.5px] border-line" aria-hidden="true" />}
                {day.name}
              </h3>
              <div className="flex flex-wrap gap-2">
                {day.times.map((time) => {
                  const pressed = picks.includes(time.key);
                  const skipped = describedBy(time.key);
                  return (
                    <button
                      key={time.key}
                      type="button"
                      className="st-time"
                      aria-pressed={pressed}
                      data-suggested={suggested.has(time.key)}
                      data-time={time.key}
                      data-first={time.first.utc}
                      data-skips={skipped ? 'true' : undefined}
                      aria-label={t('timeLabel', { time: time.label, first: time.first.label })}
                      aria-describedby={skipped}
                      onClick={() => onPress(time.key)}
                    >
                      {skipped ? <span className="st-skip-mark" aria-hidden="true"><Icon name="calendar" size={11} /></span> : null}
                      <span>{wall(time.start)}</span>
                      <small>{t('firstOn', { day: dayLabel(time.first.local.slice(0, 10), locale, { day: 'numeric', month: 'short' }) })}</small>
                    </button>
                  );
                })}
              </div>
              {notes.length > 0 ? (
                <ul className="mt-2.5 space-y-1">
                  {notes.map((note) => (
                    <li key={note.id} id={note.id} className="st-skip" data-skip>
                      <span className="st-skip-mark" aria-hidden="true"><Icon name="calendar" size={11} /></span>
                      <span>{note.text}</span>
                    </li>
                  ))}
                </ul>
              ) : null}
            </section>
          );
        })}
      </div>
      {timesReady(picks, perWeek) ? <Notice tone="brand">{t('ready')}</Notice> : null}
    </div>
  );
}

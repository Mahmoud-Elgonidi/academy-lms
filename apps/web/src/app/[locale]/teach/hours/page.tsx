import { getTranslations } from 'next-intl/server';
import type { ReactNode } from 'react';
import { ConversationPanel, CountUp, ProposalActions, ProposeButton, ShiftStepper, WeekSwitch } from '@/components/teach/shifts';
import { stageOf, type ShiftStage } from '@/lib/teach/shifts';
import { WeekView } from '@/components/teach/week-view';
import { Icon, type IconName } from '@/components/icons';
import { Badge, Card, Heading, Notice, join } from '@/components/ui';
import { get } from '@/lib/api/server';
import { addDays, dayLabel, hoursOf, zoneName } from '@/lib/format';
import type { ShiftDecision, Shifts } from '@/lib/teach/types';
import { viewerTimeFormat } from '@/lib/viewer';

/**
 * The teacher's working hours (docs/architecture 02), one thing at a time:
 * on top, the one thing that matters now (the open proposal with its step and
 * its one action, else an approved week that starts later, else a proposal
 * turned down lately); then one card with the agreed week (a switch between
 * now and the later one); "propose a change" under it while nothing is open;
 * and the history folded away at the end.
 */
const DONE = { sent: 'proposalSent', draft: 'draftSaved', accepted: 'proposalAccepted', withdrawn: 'proposalWithdrawn' } as const;

/** A proposal turned down in the last two weeks still shows on top, with its reason. */
function recentlyRejected(d: ShiftDecision | undefined): ShiftDecision | undefined {
  return d && d.status === 'rejected' && d.decided_at && Date.now() - Date.parse(d.decided_at.utc) < 14 * 24 * 3600 * 1000 ? d : undefined;
}

/** Whole days from one calendar day to another ("YYYY-MM-DD"). */
const daysBetween = (from: string, to: string) => Math.round((Date.parse(`${to}T00:00:00Z`) - Date.parse(`${from}T00:00:00Z`)) / 86_400_000);

const look: Record<Exclude<ShiftStage, 'approved' | 'rejected'>, { icon: IconName; card: string; badge: string; title: string; text: string }> = {
  draft: { icon: 'file', card: 'border-line', badge: 'bg-paper text-muted', title: 'heroDraftTitle', text: 'heroDraftText' },
  review: { icon: 'clock', card: 'border-info/40', badge: 'bg-info-soft text-info', title: 'heroReviewTitle', text: 'stageReview' },
  changes: { icon: 'alert', card: 'border-attention/50', badge: 'bg-attention-soft text-attention', title: 'heroChangesTitle', text: 'heroChangesText' },
  countered: { icon: 'chat', card: 'border-attention/50', badge: 'bg-attention-soft text-attention', title: 'heroCounteredTitle', text: 'heroCounteredText' },
};

/** The hero's head: a round icon and the sentence that says where things stand. */
function HeroHead({ icon, badge, title, text, pulse }: { icon: IconName; badge: string; title: string; text: ReactNode; pulse?: boolean }) {
  return (
    <div className="flex items-start gap-3">
      <span className={join('relative grid size-11 shrink-0 place-items-center rounded-full', badge)}>
        <Icon name={icon} size={22} />
        {pulse ? (
          <span className="absolute -end-0.5 -top-0.5 grid size-3 place-items-center" aria-hidden="true">
            <span className="pulse-soft absolute inset-0 rounded-full bg-info" />
            <span className="relative size-2 rounded-full bg-info" />
          </span>
        ) : null}
      </span>
      <div className="min-w-0">
        <h2 className="text-lg font-bold">{title}</h2>
        <div className="mt-0.5 text-sm text-muted">{text}</div>
      </div>
    </div>
  );
}

export default async function Hours({ params, searchParams }: { params: Promise<{ locale: string }>; searchParams: Promise<{ done?: string }> }) {
  const [{ locale }, { done }] = await Promise.all([params, searchParams]);
  const [t, common, nav, format, shifts] = await Promise.all([
    getTranslations('teach'),
    getTranslations('common'),
    getTranslations('nav'),
    viewerTimeFormat(),
    get<{ data: Shifts }>('/v1/teacher/shifts'),
  ]);
  const { current, proposal, earliest_start: earliest } = shifts.data;
  const upcoming = shifts.data.upcoming ?? null;
  const history = shifts.data.history ?? [];
  const hours = (minutes: number) => common('hoursAmount', { h: hoursOf(minutes) });
  const weekly = (minutes: number) => t('weeklyHours', { h: hours(minutes) });
  const labels = {
    off: t('dayOff'),
    added: t('timeAdded'),
    removed: t('timeRemoved'),
    marks: { added: t('dayAdded'), changed: t('dayChanged'), removed: t('dayRemoved') },
  };
  const nowMinutes = current.weekly_minutes ?? 0;
  const hasCurrent = current.blocks.length > 0;
  // A new proposal starts from the latest agreed week, and not before it begins.
  const latest = upcoming?.blocks ?? current.blocks;
  const upcomingDay = upcoming?.starts_on.local.slice(0, 10);
  const upcomingShort = upcomingDay ? dayLabel(upcomingDay, locale, { day: 'numeric', month: 'long' }) : '';
  const from = upcomingDay && upcomingDay > earliest.adding_time ? upcomingDay : earliest.adding_time;
  // Today on the teacher's clock: the day before the earliest a proposal may start.
  const today = addDays(earliest.adding_time, -1);
  const stage: ShiftStage | null = proposal ? stageOf(proposal.status) : null;
  const rejected = proposal ? undefined : recentlyRejected(history[0]);

  let hero: ReactNode = null;
  if (proposal && stage && stage !== 'approved' && stage !== 'rejected') {
    const l = look[stage];
    const countered = stage === 'countered';
    hero = (
      <section className={join('appear rounded-card border bg-white p-5', l.card)} data-testid="shifts-hero" data-stage={stage}>
        <HeroHead icon={l.icon} badge={l.badge} title={t(l.title)} text={t(l.text)} pulse={stage === 'review'} />
        <div className="mt-5">
          <ShiftStepper stage={stage} />
        </div>
        <details className="group mt-5 rounded-control border border-line" open={countered}>
          <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-4 text-sm [&::-webkit-details-marker]:hidden">
            <span className="font-semibold">{countered ? t('counterWeek') : t('proposedWeek')}</span>
            <span className="flex items-center gap-2 text-muted">
              {t('startsOn', { date: dayLabel(proposal.effective_from, locale) })} · {weekly(proposal.week.weekly_minutes ?? 0)}
              <Icon name="chevron" size={16} className="rotate-90 motion-safe:transition-transform group-open:-rotate-90" />
            </span>
          </summary>
          <div className="border-t border-line px-4 pb-3">
            <WeekView
              blocks={proposal.week.blocks}
              before={countered && proposal.previous_week ? proposal.previous_week.blocks : latest}
              locale={locale}
              format={format}
              labels={labels}
            />
          </div>
        </details>
        {proposal.note ? <p className="mt-4 whitespace-pre-line text-sm text-muted">{proposal.note}</p> : null}
        <ProposalActions proposal={proposal} start={latest} earliest={earliest} />
        {stage !== 'draft' ? (
          <div className="mt-4">
            <ConversationPanel proposal={proposal} />
          </div>
        ) : null}
      </section>
    );
  } else if (upcoming && upcomingDay) {
    const before = Number(hoursOf(nowMinutes));
    const after = Number(hoursOf(upcoming.weekly_minutes));
    const changes = hasCurrent && nowMinutes !== upcoming.weekly_minutes;
    hero = (
      <section className="appear rounded-card border border-brand-line bg-brand-soft/60 p-5" data-testid="shifts-upcoming">
        <HeroHead
          icon="calendar"
          badge="bg-white text-brand-strong"
          title={t('heroUpcomingTitle', { date: upcoming.starts_on.label })}
          text={`${t('inDays', { n: Math.max(1, daysBetween(today, upcomingDay)) })} · ${t('upcomingNote')}`}
        />
        <p className="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1 ps-14 font-heading">
          <span className="sr-only">{changes ? t('hoursChange', { before: hours(nowMinutes), after: hours(upcoming.weekly_minutes) }) : weekly(upcoming.weekly_minutes)}</span>
          <span aria-hidden="true" className="contents">
            {changes ? (
              <>
                <span className="text-2xl font-bold text-muted">
                  <CountUp to={before} />
                </span>
                <Icon name="chevron" size={18} className="self-center text-muted rtl:-scale-x-100" />
              </>
            ) : null}
            <span className="text-4xl font-bold text-brand-strong">
              <CountUp to={after} />
            </span>
            <span className="text-sm text-muted">{t('hoursAWeek')}</span>
          </span>
        </p>
      </section>
    );
  } else if (rejected) {
    hero = (
      <section className="appear rounded-card border border-danger/40 bg-white p-5" data-testid="shifts-hero" data-stage="rejected">
        <HeroHead
          icon="x"
          badge="bg-danger-soft text-danger"
          title={t('heroRejectedTitle')}
          text={
            <>
              {rejected.reason ? <span className="block text-ink">{t('reasonLine', { reason: rejected.reason })}</span> : null}
              <span className="block">{t('heroRejectedText')}</span>
            </>
          }
        />
      </section>
    );
  }

  const currentWeek = <WeekView blocks={current.blocks} locale={locale} format={format} labels={labels} />;
  let week: ReactNode;
  if (upcoming && hasCurrent) {
    week = (
      <WeekSwitch
        label={t('whichWeek')}
        weeks={[
          { key: 'now', tab: t('weekNow'), hours: weekly(nowMinutes), content: currentWeek },
          {
            key: 'upcoming',
            tab: t('weekFrom', { date: upcomingShort }),
            hours: weekly(upcoming.weekly_minutes),
            content: <WeekView blocks={upcoming.blocks} before={current.blocks} locale={locale} format={format} labels={labels} />,
          },
        ]}
      />
    );
  } else if (upcoming) {
    week = (
      <>
        <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
          <p className="text-sm text-muted">{t('noHoursBefore', { date: upcomingShort })}</p>
          <span className="text-sm text-muted">{weekly(upcoming.weekly_minutes)}</span>
        </div>
        <WeekView blocks={upcoming.blocks} locale={locale} format={format} labels={labels} />
      </>
    );
  } else if (hasCurrent) {
    week = (
      <>
        <p className="mb-3 text-end text-sm text-muted">{weekly(nowMinutes)}</p>
        {currentWeek}
      </>
    );
  } else {
    week = <p className="text-muted">{t('noHoursYet')}</p>;
  }

  return (
    <div>
      <Heading title={nav('hours')} hint={t('hoursOnClock', { zone: zoneName(shifts.data.timezone, locale) })} />
      {done && done in DONE ? (
        <div className="mb-4">
          <Notice tone="brand">{t(DONE[done as keyof typeof DONE])}</Notice>
        </div>
      ) : null}

      <div className="space-y-5">
        {hero}

        <Card className="appear">
          <h2 className="mb-3 font-semibold">{t('weekTitle')}</h2>
          <div data-testid="shifts-week">{week}</div>
        </Card>

        {proposal ? null : <ProposeButton start={latest} from={from} earliest={earliest} note={upcoming ? t('builtOnUpcoming', { date: upcomingShort }) : undefined} />}

        {history.length > 0 ? (
          <details className="group rounded-card border border-line bg-white" data-testid="shifts-history">
            <summary className="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-5 font-semibold [&::-webkit-details-marker]:hidden">
              {t('history', { n: history.length })}
              <Icon name="chevron" size={18} className="rotate-90 text-muted motion-safe:transition-transform group-open:-rotate-90" />
            </summary>
            <ul className="stagger divide-y divide-line border-t border-line">
              {history.map((d) => (
                <li key={d.id} className={join('flex items-start gap-3 px-5 py-3', d.status === 'superseded' && 'text-muted')}>
                  <Badge tone={d.status === 'rejected' ? 'danger' : d.status === 'approved' ? 'brand' : 'muted'}>{d.status === 'superseded' ? t('superseded') : d.status_label}</Badge>
                  <div className="min-w-0 text-sm">
                    <p>{t('askedToStart', { date: dayLabel(d.effective_from, locale) })}</p>
                    {d.decided_at ? <p className="text-muted">{d.decided_at.label}</p> : null}
                    {d.reason ? <p className="text-danger">{t('reasonLine', { reason: d.reason })}</p> : null}
                  </div>
                </li>
              ))}
            </ul>
          </details>
        ) : null}
      </div>
    </div>
  );
}

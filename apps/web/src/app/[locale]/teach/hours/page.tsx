import { getTranslations } from 'next-intl/server';
import { ConversationPanel, ProposalActions, ProposeButton, ShiftStepper, stageOf, type ShiftStage } from '@/components/teach/shifts';
import { WeekView } from '@/components/teach/week-view';
import { Badge, Card, Heading, Notice, SectionTitle } from '@/components/ui';
import { get } from '@/lib/api/server';
import { dayLabel, hoursOf, zoneName } from '@/lib/format';
import type { ShiftDecision, Shifts } from '@/lib/teach/types';
import { viewerTimeFormat } from '@/lib/viewer';

/**
 * The teacher's working hours, in steps (docs/architecture 02): the week in
 * force, an approved one that starts later (with how the hours change), the
 * proposal where it stands with the one thing to do now, its conversation
 * beside the page, and the last decisions.
 */
const DONE = { sent: 'proposalSent', draft: 'draftSaved', accepted: 'proposalAccepted', withdrawn: 'proposalWithdrawn' } as const;

/** A decision from the last two weeks still shows as where the last proposal ended. */
function recent(d: ShiftDecision | undefined): ShiftDecision | undefined {
  const now = Date.now();
  return d && d.status !== 'superseded' && d.decided_at && now - Date.parse(d.decided_at.utc) < 14 * 24 * 3600 * 1000 ? d : undefined;
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
  const labels = { off: t('dayOff'), added: t('timeAdded'), removed: t('timeRemoved') };
  // A new proposal starts from the latest agreed week, and not before it begins.
  const latest = upcoming?.blocks ?? current.blocks;
  const upcomingDay = upcoming?.starts_on.local.slice(0, 10);
  const from = upcomingDay && upcomingDay > earliest.adding_time ? upcomingDay : earliest.adding_time;
  const last = recent(history[0]);

  const stage: ShiftStage | null = proposal ? stageOf(proposal.status) : null;
  const hint = stage
    ? {
        draft: { tone: 'muted', text: t('stageDraft') },
        review: { tone: 'info', text: t('stageReview') },
        changes: { tone: 'attention', text: t('stageChanges') },
        countered: { tone: 'attention', text: t('academyCounter') },
        approved: { tone: 'brand', text: t('stageApproved') },
        rejected: { tone: 'danger', text: t('stageRejected') },
      }[stage]
    : null;

  return (
    <div>
      <Heading title={nav('hours')} hint={t('hoursOnClock', { zone: zoneName(shifts.data.timezone, locale) })} />
      {done && done in DONE ? (
        <div className="mb-4">
          <Notice tone="brand">{t(DONE[done as keyof typeof DONE])}</Notice>
        </div>
      ) : null}

      <Card>
        <div className="mb-4 flex flex-wrap items-baseline justify-between gap-2">
          <h2 className="font-semibold">{t('currentWeek')}</h2>
          <span className="text-sm text-muted">{weekly(current.weekly_minutes ?? 0)}</span>
        </div>
        {current.blocks.length === 0 ? <p className="text-muted">{t('noHoursYet')}</p> : <WeekView blocks={current.blocks} locale={locale} format={format} labels={{ off: t('dayOff') }} />}
      </Card>

      {upcoming ? (
        <section className="mt-5 rounded-card border border-brand-line bg-brand-soft/60 p-5" data-testid="shifts-upcoming">
          <h2 className="text-lg font-bold text-brand-strong">{t('upcomingTitle', { date: upcoming.starts_on.label })}</h2>
          <p className="mt-1 text-sm text-muted">{t('upcomingNote')}</p>
          <p className="mt-3">
            <Badge tone="brand">
              {upcoming.weekly_minutes === (current.weekly_minutes ?? 0)
                ? t('hoursSame', { h: hours(upcoming.weekly_minutes) })
                : t('hoursChange', { before: hours(current.weekly_minutes ?? 0), after: hours(upcoming.weekly_minutes) })}
            </Badge>
          </p>
          <div className="mt-4 rounded-control bg-white p-4">
            <WeekView blocks={upcoming.blocks} before={current.blocks.length > 0 ? current.blocks : undefined} locale={locale} format={format} labels={labels} />
          </div>
        </section>
      ) : null}

      <SectionTitle>{t('yourProposal')}</SectionTitle>
      {proposal && stage && hint ? (
        <Card className={proposal.waiting_on === 'teacher' ? 'border-attention/50' : undefined}>
          <ShiftStepper stage={stage} />
          <div className="mt-5">
            <Notice tone={hint.tone as 'muted' | 'info' | 'attention' | 'brand' | 'danger'}>{hint.text}</Notice>
          </div>
          <p className="mb-4 mt-4 text-sm text-muted">
            {t('startsOn', { date: dayLabel(proposal.effective_from, locale) })} · {weekly(proposal.week.weekly_minutes ?? 0)}
          </p>
          <WeekView blocks={proposal.week.blocks} before={stage === 'countered' && proposal.previous_week ? proposal.previous_week.blocks : latest} locale={locale} format={format} labels={labels} />
          {proposal.note ? <p className="mt-4 whitespace-pre-line text-sm text-muted">{proposal.note}</p> : null}
          <ProposalActions proposal={proposal} start={latest} earliest={earliest} />
        </Card>
      ) : (
        <Card>
          {last ? (
            <div className="mb-5 space-y-4">
              <ShiftStepper stage={last.status === 'rejected' ? 'rejected' : 'approved'} />
              <Notice tone={last.status === 'rejected' ? 'danger' : 'brand'}>
                {last.status === 'rejected' ? t('stageRejected') : t('stageApproved')}
                {last.reason ? <span className="block">{t('reasonLine', { reason: last.reason })}</span> : null}
              </Notice>
            </div>
          ) : (
            <p className="mb-4 text-muted">{t('newProposalHint')}</p>
          )}
          <ProposeButton start={latest} from={from} earliest={earliest} />
        </Card>
      )}
      {proposal && proposal.status !== 'draft' ? (
        <div className="mt-3">
          <ConversationPanel proposal={proposal} />
        </div>
      ) : null}

      {history.length > 0 ? (
        <>
          <SectionTitle>{t('earlierProposals')}</SectionTitle>
          <Card className="p-0">
            <ul className="divide-y divide-line">
              {history.map((d) => (
                <li key={d.id} className="flex items-start gap-3 px-5 py-3">
                  <Badge tone={d.status === 'rejected' ? 'danger' : d.status === 'approved' ? 'brand' : 'muted'}>{d.status_label}</Badge>
                  <div className="min-w-0 text-sm">
                    <p>{t('askedToStart', { date: dayLabel(d.effective_from, locale) })}</p>
                    {d.decided_at ? <p className="text-muted">{d.decided_at.label}</p> : null}
                    {d.reason ? <p className="text-danger">{t('reasonLine', { reason: d.reason })}</p> : null}
                  </div>
                </li>
              ))}
            </ul>
          </Card>
        </>
      ) : null}
    </div>
  );
}

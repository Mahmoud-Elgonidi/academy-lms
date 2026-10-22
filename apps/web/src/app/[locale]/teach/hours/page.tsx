import { getTranslations } from 'next-intl/server';
import { Conversation, ProposalActions, ProposeButton } from '@/components/teach/shifts';
import { WeekView } from '@/components/teach/week-view';
import { Badge, Card, Heading, Notice, SectionTitle } from '@/components/ui';
import { get } from '@/lib/api/server';
import { dayLabel, hoursOf, zoneName } from '@/lib/format';
import type { Shifts } from '@/lib/teach/types';
import { viewerTimeFormat } from '@/lib/viewer';

/**
 * The teacher's working hours: the week the academy approved, and their own
 * proposal for a new one (sent, waiting, or countered by the academy, with
 * what changed), with the conversation about it (docs/architecture 02).
 */
const DONE = { sent: 'proposalSent', draft: 'draftSaved', accepted: 'proposalAccepted', withdrawn: 'proposalWithdrawn' } as const;

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
  const weekly = (minutes: number) => t('weeklyHours', { h: common('hoursAmount', { h: hoursOf(minutes) }) });
  const labels = { off: t('dayOff'), added: t('timeAdded'), removed: t('timeRemoved') };
  const mine = proposal !== null && proposal.waiting_on === 'teacher';

  return (
    <div>
      <Heading title={nav('hours')} hint={t('hoursOnClock', { zone: zoneName(shifts.data.timezone, locale) })} />
      {done && done in DONE ? (
        <div className="mb-4">
          <Notice tone="brand">{t(DONE[done as keyof typeof DONE])}</Notice>
        </div>
      ) : null}

      {proposal ? (
        <Card className={mine ? 'mb-5 border-attention/50' : 'mb-5 border-brand-line'}>
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h2 className="font-semibold">{t('yourProposal')}</h2>
            <div className="flex flex-wrap gap-1.5">
              <Badge tone={mine ? 'attention' : 'info'}>{proposal.status_label}</Badge>
              {proposal.waiting_on ? <Badge>{mine ? t('waitingOnYou') : t('waitingOnAcademy')}</Badge> : null}
            </div>
          </div>
          <p className="mb-4 text-sm text-muted">
            {t('startsOn', { date: dayLabel(proposal.effective_from, locale) })} · {weekly(proposal.week.weekly_minutes ?? 0)}
          </p>
          {proposal.status === 'countered' && proposal.previous_week ? <p className="mb-3 text-sm font-semibold">{t('academyCounter')}</p> : null}
          <WeekView blocks={proposal.week.blocks} before={proposal.status === 'countered' && proposal.previous_week ? proposal.previous_week.blocks : current.blocks} locale={locale} format={format} labels={labels} />
          {proposal.note ? <p className="mt-4 whitespace-pre-line text-sm text-muted">{proposal.note}</p> : null}
          <ProposalActions proposal={proposal} current={current.blocks} earliest={earliest} />
        </Card>
      ) : null}

      <Card>
        <div className="mb-4 flex flex-wrap items-baseline justify-between gap-2">
          <h2 className="font-semibold">{t('currentWeek')}</h2>
          <span className="text-sm text-muted">{weekly(current.weekly_minutes ?? 0)}</span>
        </div>
        {current.blocks.length === 0 ? <p className="text-muted">{t('noHoursYet')}</p> : <WeekView blocks={current.blocks} locale={locale} format={format} labels={{ off: t('dayOff') }} />}
        {proposal ? null : (
          <div className="mt-5 border-t border-line pt-5">
            <ProposeButton current={current.blocks} earliest={earliest} />
          </div>
        )}
      </Card>

      {proposal ? (
        <>
          <SectionTitle>{t('conversation')}</SectionTitle>
          <Card>
            <Conversation comments={proposal.comments} />
          </Card>
        </>
      ) : null}
    </div>
  );
}

import { notFound } from 'next/navigation';
import { getLocale, getTranslations } from 'next-intl/server';
import { Icon } from '@/components/icons';
import { Icon as SiteIcon } from '@/components/site/icons';
import { NextTrialPanel } from '@/components/site/trial/next-trial';
import { NotNow, ReportView, StartWays } from '@/components/site/trial/report';
import { Link } from '@/i18n/navigation';
import { get, read } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import { seesMoney } from '@/lib/family/rules';
import { momentNow, type FamilyTrial, type TrialReport } from '@/lib/site/report';
import { academySite } from '@/lib/site/server';
import { viewerTimeFormat } from '@/lib/viewer';

type Props = { params: Promise<{ session: string }> };

/**
 * A free trial of the learner on screen: its report as the family's link
 * shows it (the classic look: the plan, its price, the offer's time left, the
 * way to start, and the second trial with another teacher, booked here); a
 * student's own look reads the report alone. A trial that did not take place
 * offers a new time instead (the classic look), or says why not.
 */
export default async function FamilyTrial({ params }: Props) {
  const { session } = await params;
  if (!/^[0-9a-f-]{36}$/i.test(session)) notFound();
  const [fam, locale, t, site, timeFormat] = await Promise.all([family(), getLocale(), getTranslations('site.report'), academySite(), viewerTimeFormat()]);
  const classic = seesMoney(fam.stage);
  const found = await read<{ data: TrialReport; viewer_stage: string }>('/v1/family/trials/{session}', { params: { session } });
  const now = momentNow();
  const whatsapp = site?.whatsapp ?? null;
  const academy = site?.academy.name ?? '';
  const back = (
    <Link href="/family/trials" className="mb-4 inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-brand">
      <Icon name="chevron" size={18} className="ltr:-scale-x-100" />{t('family.back')}
    </Link>
  );
  const done = { href: `/${locale}/family/trials`, label: t('family.back') };

  if (!found.ok) {
    // No report (yet): a trial that did not take place may be booked again from here.
    const list = fam.current ? await get<{ data: FamilyTrial[] }>('/v1/family/trials', { query: { learner_id: fam.current.id } }) : { data: [] };
    const trial = list.data.find((item) => item.id === session);
    if (!trial || (trial.status !== 'missed' && trial.status !== 'cancelled')) notFound();
    const ours = trial.outcome === 'teacher_absent' || trial.outcome === 'academy';
    const learner = fam.current?.name.split(' ')[0] ?? '';
    const happened = t(ours ? `rebook.${trial.outcome as 'academy'}` : 'rebook.student_absent', { learner, subject: trial.subject.name, when: trial.starts_at?.label ?? '' });
    return (
      <div className="ls ls-academy lr-embed">
        {back}
        <div className="lr">
          <section className="lt-card" aria-labelledby="lr-missed-title">
            <header className="lt-step-head">
              <p className="lt-kicker">{t('rebook.kicker')}</p>
              <h1 id="lr-missed-title">{t('family.missedTitle', { subject: trial.subject.name })}</h1>
              <p className="lt-muted">{happened}</p>
            </header>
            {ours ? <p className="lr-chip"><SiteIcon name="shield" />{t('rebook.noCount')}</p> : null}
          </section>
          {classic && trial.next ? (trial.next.available ? (
            <NextTrialPanel source={{ from: 'family', session }} kind="rebook" learner={learner} subject={trial.subject.name}
              academy={academy} whatsapp={whatsapp} timeFormat={timeFormat} zone={trial.starts_at?.timezone} done={done} />
          ) : (
            <NotNow title={t('rebook.cantTitle')} text={trial.next.why_not ?? ''} whatsapp={whatsapp} />
          )) : null}
        </div>
      </div>
    );
  }

  const report = found.data.data;
  const next = report.next_trial;
  return (
    <div className="ls ls-academy lr-embed">
      {back}
      <ReportView
        report={report}
        look={classic ? 'family' : 'student'}
        locale={locale}
        now={now}
        start={<StartWays report={report} whatsapp={whatsapp} help={`/${locale}/family/help`} lessons={`/${locale}/family/lessons`} />}
        next={next ? (
          <div id="next" className="scroll-mt-24">
            {next.available ? (
              <NextTrialPanel source={{ from: 'family', session }} kind="second" learner={report.learner} subject={report.subject.name}
                academy={academy} whatsapp={whatsapp} timeFormat={timeFormat} zone={report.held_at?.timezone} until={next.until?.label} done={done} />
            ) : (
              <NotNow title={t('next.notNow')} text={next.why_not ?? ''} whatsapp={whatsapp} />
            )}
          </div>
        ) : null}
      />
    </div>
  );
}

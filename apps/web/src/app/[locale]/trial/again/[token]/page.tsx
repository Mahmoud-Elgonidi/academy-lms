import type { Metadata } from 'next';
import { getLocale, getTranslations } from 'next-intl/server';
import { Icon } from '@/components/site/icons';
import { NextTrialPanel } from '@/components/site/trial/next-trial';
import { NotNow } from '@/components/site/trial/report';
import { CalmCard, TrialShell } from '@/components/site/trial/shell';
import { read } from '@/lib/api/server';
import type { RebookInfo } from '@/lib/site/report';
import { academySite } from '@/lib/site/server';
import { timeFormatOf } from '@/lib/time';
import { branding } from '@/lib/viewer';
import '@/components/site/trial/report.css';

type Props = { params: Promise<{ locale: string; token: string }> };

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations('site.report.rebook');
  return { title: t('metaTitle'), robots: { index: false, follow: false }, referrer: 'no-referrer' };
}

/**
 * "Book another time" after a free trial that did not take place (Crm's
 * ReportLinks: /{locale}/trial/again/{token}), with no sign-in: what happened
 * in one kind line (missed by the learner; or the teacher's absence or the
 * academy's cancellation, which never counts as their free trial), then the
 * times to book a new one. Every reason it cannot be booked is a calm card
 * with what to do next; a link used already or whose time passed says so.
 */
export default async function RebookPage({ params }: Props) {
  const { token } = await params;
  const [locale, t, look, site] = await Promise.all([getLocale(), getTranslations('site.report'), branding(), academySite()]);
  const found = await read<{ data: RebookInfo }>('/v1/site/trials/rebook/{token}', { params: { token } });
  const whatsapp = site?.whatsapp ?? null;
  const academy = site?.academy.name ?? (locale === 'en' ? look.name.en : look.name.ar);
  const path = `/trial/again/${token}`;

  if (!found.ok) {
    const ended = found.problem.status === 410;
    return (
      <TrialShell look={look} path={path}>
        <CalmCard icon={ended ? 'check' : 'clock'} title={ended ? t('expired.title') : t('rebookGone.title')}
          text={ended && found.problem.title ? found.problem.title : t('rebookGone.text')} whatsapp={whatsapp} />
      </TrialShell>
    );
  }
  const info = found.data.data;
  const when = info.was_at?.label ?? '';
  const ours = info.outcome === 'teacher_absent' || info.outcome === 'academy';
  const happened = t(ours ? `rebook.${info.outcome as 'academy'}` : 'rebook.student_absent', { learner: info.learner, subject: info.subject.name, when });

  return (
    <TrialShell look={look} path={path}>
      <div className="lr">
        <section className="lt-card" aria-labelledby="lr-rebook-title">
          <header className="lt-step-head">
            <p className="lt-kicker">{t('rebook.kicker')}</p>
            <h1 id="lr-rebook-title">{t('rebook.title', { learner: info.learner })}</h1>
            <p className="lt-muted">{happened}</p>
          </header>
          {ours ? <p className="lr-chip"><Icon name="shield" />{t('rebook.noCount')}</p> : null}
        </section>
        {info.available ? (
          <NextTrialPanel
            source={{ from: 'rebook', token }}
            kind="rebook"
            learner={info.learner}
            subject={info.subject.name}
            academy={academy}
            whatsapp={whatsapp}
            timeFormat={timeFormatOf(look.time_format)}
            zone={info.timezone}
          />
        ) : (
          <NotNow title={t('rebook.cantTitle')} text={info.why_not ?? ''} whatsapp={whatsapp} />
        )}
      </div>
    </TrialShell>
  );
}

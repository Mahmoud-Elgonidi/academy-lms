import type { Metadata } from 'next';
import { getLocale, getTranslations } from 'next-intl/server';
import { NextTrialPanel } from '@/components/site/trial/next-trial';
import { NotNow, ReportView, StartWays } from '@/components/site/trial/report';
import { CalmCard, TrialShell } from '@/components/site/trial/shell';
import { read } from '@/lib/api/server';
import { momentNow, type TrialReport } from '@/lib/site/report';
import { academySite } from '@/lib/site/server';
import { timeFormatOf } from '@/lib/time';
import { branding } from '@/lib/viewer';

type Props = { params: Promise<{ locale: string; token: string }> };

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations('site.report');
  return { title: t('metaTitle'), robots: { index: false, follow: false }, referrer: 'no-referrer' };
}

/**
 * The family's report after a free trial, from the link in "the report is
 * ready" (Crm's ReportLinks: /{locale}/trial/report/{token}), with no sign-in:
 * the report card, the recommended plan with its price and the offer's time
 * left, the way to start, and the free second trial with another teacher. A
 * link that names no report is not found; one whose time passed says so and
 * leads to signing in, where the report is kept.
 */
export default async function TrialReportPage({ params }: Props) {
  const { token } = await params;
  const [locale, t, look, site] = await Promise.all([getLocale(), getTranslations('site.report'), branding(), academySite()]);
  const found = await read<{ data: TrialReport }>('/v1/site/trials/report/{token}', { params: { token } });
  const now = momentNow();
  const whatsapp = site?.whatsapp ?? null;
  const academy = site?.academy.name ?? (locale === 'en' ? look.name.en : look.name.ar);
  const path = `/trial/report/${token}`;
  const signInHome = `/${locale}/sign-in`;

  if (!found.ok) {
    const ended = found.problem.status === 410;
    return (
      <TrialShell look={look} path={path}>
        <CalmCard icon="clock" title={ended ? t('expired.title') : t('gone.title')} text={ended && found.problem.title ? found.problem.title : t('gone.text')}
          whatsapp={whatsapp} home={signInHome} homeLabel={t('signIn')} />
      </TrialShell>
    );
  }
  const report = found.data.data;
  const signIn = `/${locale}/sign-in?next=${encodeURIComponent(`/${locale}/family/trials/${report.id}`)}`;
  const next = report.next_trial;
  return (
    <TrialShell look={look} path={path}>
      <ReportView
        report={report}
        look="family"
        locale={locale}
        now={now}
        start={<StartWays report={report} whatsapp={whatsapp} signIn={signIn} />}
        next={next ? (next.available ? (
          <NextTrialPanel
            source={{ from: 'report', token }}
            kind="second"
            learner={report.learner}
            subject={report.subject.name}
            academy={academy}
            whatsapp={whatsapp}
            timeFormat={timeFormatOf(look.time_format)}
            zone={report.held_at?.timezone}
            until={next.until?.label}
          />
        ) : (
          <NotNow title={t('next.notNow')} text={next.why_not ?? ''} whatsapp={whatsapp} />
        )) : null}
      />
    </TrialShell>
  );
}

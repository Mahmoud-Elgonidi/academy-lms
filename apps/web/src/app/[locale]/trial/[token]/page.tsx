import type { Metadata } from 'next';
import { getLocale, getTranslations } from 'next-intl/server';
import { CalmCard, TrialShell } from '@/components/site/trial/shell';
import { PickTrial, type PickRequest } from '@/components/site/trial/trial-pages';
import { read } from '@/lib/api/server';
import { academySite } from '@/lib/site/server';
import { timeFormatOf } from '@/lib/time';
import { branding } from '@/lib/viewer';

type Props = { params: Promise<{ locale: string; token: string }> };

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations('site.trial.pick');
  return { title: t('title'), robots: { index: false, follow: false }, referrer: 'no-referrer' };
}

/**
 * "Pick a time" from the instant reply to a free trial request (Site's
 * PickLinks: /{locale}/trial/{token}): the request's subject and learner, and
 * the free times to book one with no code (the link went to their number).
 * A link that names no request waiting for a time (booked already, two weeks
 * old, the academy books trials itself) says so calmly, with the ways on.
 */
export default async function PickTrialPage({ params }: Props) {
  const { token } = await params;
  const [locale, t, look, site] = await Promise.all([getLocale(), getTranslations('site.trial'), branding(), academySite()]);
  const found = await read<{ data: PickRequest }>('/v1/site/trials/pick/{token}', { params: { token } });
  const whatsapp = site?.whatsapp ?? null;
  const academy = site?.academy.name ?? (locale === 'en' ? look.name.en : look.name.ar);
  const path = `/trial/${token}`;

  if (!found.ok) {
    return (
      <TrialShell look={look} path={path}>
        <CalmCard icon="clock" title={t('pick.gone.title')} text={t('pick.gone.text')} whatsapp={whatsapp} whatsappWords={t('whatsappWords')}
          home={site ? `/${locale}#join` : undefined} homeLabel={t('pick.gone.again')} />
      </TrialShell>
    );
  }
  const request = found.data.data;
  if (!request.subject) {
    return (
      <TrialShell look={look} path={path}>
        <CalmCard icon="chat" title={t('pick.noSubject.title')} text={t('pick.noSubject.text', { hours: site?.trials?.response_hours ?? 24 })}
          whatsapp={whatsapp} whatsappWords={t('whatsappWords')} />
      </TrialShell>
    );
  }
  return (
    <TrialShell look={look} path={path}>
      <PickTrial request={request} token={token} academy={academy} whatsapp={whatsapp} timeFormat={timeFormatOf(look.time_format)} locale={locale} />
    </TrialShell>
  );
}

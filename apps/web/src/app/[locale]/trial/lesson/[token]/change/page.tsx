import type { Metadata } from 'next';
import { getLocale, getTranslations } from 'next-intl/server';
import { CalmCard, TrialShell } from '@/components/site/trial/shell';
import { ChangeTrial } from '@/components/site/trial/trial-pages';
import { academySite } from '@/lib/site/server';
import { timeFormatOf } from '@/lib/time';
import { branding } from '@/lib/viewer';
import { lessonOf } from '../lesson';

type Props = { params: Promise<{ locale: string; token: string }> };

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations('site.trial.lesson.change');
  return { title: t('title'), robots: { index: false, follow: false }, referrer: 'no-referrer' };
}

/**
 * Changing a free trial's time from the family's own link (Scheduling's TrialLinks: /{locale}/trial/lesson/{token}/change), with no sign-in.
 * A link that names no coming trial (it started, was cancelled, or is old) says so calmly, with the ways on.
 */
export default async function ChangeTrialPage({ params }: Props) {
  const { token } = await params;
  const [locale, t, look, site, lesson] = await Promise.all([getLocale(), getTranslations('site.trial'), branding(), academySite(), lessonOf(token)]);
  const whatsapp = site?.whatsapp ?? null;
  const academy = site?.academy.name ?? (locale === 'en' ? look.name.en : look.name.ar);
  const path = `/trial/lesson/${token}/change`;
  return (
    <TrialShell look={look} path={path}>
      {lesson ? (
        <ChangeTrial lesson={lesson} token={token} academy={academy} whatsapp={whatsapp} timeFormat={timeFormatOf(look.time_format)} locale={locale} />
      ) : (
        <CalmCard icon="clock" title={t('lesson.gone.title')} text={t('lesson.gone.text')} whatsapp={whatsapp} whatsappWords={t('whatsappWords')}
          home={site ? `/${locale}#join` : undefined} homeLabel={t('lesson.gone.again')} />
      )}
    </TrialShell>
  );
}

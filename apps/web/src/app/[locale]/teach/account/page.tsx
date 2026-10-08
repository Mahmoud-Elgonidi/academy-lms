import { getLocale, getTranslations } from 'next-intl/server';
import { SocialLinks } from '@/components/social-links';
import { Heading, Notice } from '@/components/ui';

const OUTCOMES = ['linked', 'taken', 'expired', 'denied', 'failed', 'off'] as const;

type Props = { searchParams: Promise<{ social?: string }> };

/** My account: signing in with Google or Apple (linked, or link it). */
export default async function Account({ searchParams }: Props) {
  const [{ social }, t, locale] = await Promise.all([searchParams, getTranslations('account'), getLocale()]);
  const outcome = (OUTCOMES as readonly string[]).includes(social ?? '') ? (social as (typeof OUTCOMES)[number]) : null;
  return (
    <div className="space-y-5">
      <Heading title={t('title')} />
      {outcome ? <Notice tone={outcome === 'linked' ? 'brand' : outcome === 'denied' ? 'attention' : 'danger'}>{t(`outcome.${outcome}`)}</Notice> : null}
      <SocialLinks back={`/${locale}/teach/account`} />
    </div>
  );
}

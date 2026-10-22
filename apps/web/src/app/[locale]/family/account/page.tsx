import { getLocale, getTranslations } from 'next-intl/server';
import { ClockSetting } from '@/components/clock-setting';
import { ContactChange } from '@/components/contact-change';
import { SocialLinks } from '@/components/social-links';
import { Heading, Notice } from '@/components/ui';
import { get } from '@/lib/api/server';
import { timeFormatOf, type TimeFormat } from '@/lib/time';

const OUTCOMES = ['linked', 'taken', 'expired', 'denied', 'failed', 'off'] as const;

type Props = { searchParams: Promise<{ social?: string }> };

/** My account: the email and mobile number (a new one by code), how I read a clock (12 or 24 hours, or the academy's), and signing in with Google or Apple (linked, or link it). */
export default async function Account({ searchParams }: Props) {
  const [{ social }, t, locale, me] = await Promise.all([
    searchParams,
    getTranslations('account'),
    getLocale(),
    get<{ data: { email: string | null; phone: string | null; can_change_contact: boolean; time_format_choice: TimeFormat | null; academy: { time_format: TimeFormat } | null } }>('/v1/me'),
  ]);
  const outcome = (OUTCOMES as readonly string[]).includes(social ?? '') ? (social as (typeof OUTCOMES)[number]) : null;
  return (
    <div className="space-y-5">
      <Heading title={t('title')} />
      {outcome ? <Notice tone={outcome === 'linked' ? 'brand' : outcome === 'denied' ? 'attention' : 'danger'}>{t(`outcome.${outcome}`)}</Notice> : null}
      <ContactChange email={me.data.email} phone={me.data.phone} canChange={me.data.can_change_contact} />
      <ClockSetting choice={me.data.time_format_choice ?? null} academy={timeFormatOf(me.data.academy?.time_format)} />
      <SocialLinks back={`/${locale}/family/account`} />
    </div>
  );
}

import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { CardsManager } from '@/components/family/cards';
import { Icon } from '@/components/icons';
import { RefreshAt } from '@/components/live-refresh';
import { Link, redirect } from '@/i18n/navigation';
import { get } from '@/lib/api/server';
import type { Cards } from '@/lib/family/cards';
import { family } from '@/lib/family/context';
import { seesMoney } from '@/lib/family/rules';

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations('cards');
  return { title: t('title') };
}

/**
 * The payer's saved card and automatic renewal (docs/architecture/02,
 * section 27, J5b; the classic look only): the card (brand, last four,
 * expiry, a warning when it expires soon), the switch (turning it off says
 * what happens first), removing the card (likewise), the automatic charges
 * coming with their day and amount, and, with no card yet, how one is saved
 * at the next payment. Live on the payments signal; a coming charge's moment
 * refreshes the page too.
 */
export default async function CardsPage({ params }: { params: Promise<{ locale: string }> }) {
  const [{ locale }, t, fam] = await Promise.all([params, getTranslations('cards'), family()]);
  if (!seesMoney(fam.stage)) redirect({ href: '/family', locale });
  const cards = (await get<{ data: Cards }>('/v1/family/cards')).data;
  const moments = cards.coming.map((c) => c.at?.utc).filter((at): at is string => Boolean(at));

  return (
    <div className="space-y-5" data-cards-page>
      {moments.length > 0 ? <RefreshAt moments={moments} /> : null}
      <Link href="/family/money" className="inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-brand">
        <Icon name="chevron" size={18} className="ltr:-scale-x-100" />
        {t('back')}
      </Link>
      <header>
        <h1 className="text-2xl font-bold">{t('title')}</h1>
        <p className="mt-1 text-muted">{t('hint')}</p>
      </header>
      <CardsManager cards={cards} />
    </div>
  );
}

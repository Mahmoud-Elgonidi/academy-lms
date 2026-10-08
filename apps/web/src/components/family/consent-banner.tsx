import { getTranslations } from 'next-intl/server';
import { Icon } from '@/components/icons';
import { Link } from '@/i18n/navigation';

/** On a guardian's home: the children waiting for their consent, and the way to give it. */
export async function ConsentBanner({ names }: { names: string[] }) {
  const t = await getTranslations('privacy');
  const list = names.join(t('nameSeparator'));
  return (
    <Link
      href="/family/privacy"
      className="mb-4 flex flex-wrap items-center gap-3 rounded-card border border-attention/30 bg-attention-soft px-4 py-3 text-sm text-ink hover:border-attention"
    >
      <Icon name="shield" className="text-attention" />
      <span className="flex-1">{names.length === 1 ? t('banner', { names: list }) : t('bannerMany', { names: list })}</span>
      <strong className="underline underline-offset-4">{t('bannerAction')}</strong>
    </Link>
  );
}

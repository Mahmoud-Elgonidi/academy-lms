import { useTranslations } from 'next-intl';
import { Icon } from '@/components/icons';
import { Heading } from '@/components/ui';

/**
 * A Quran page while the academy's plan leaves the Quran out (the API's 403
 * feature-not-in-plan): a calm word in place of the page, never an error.
 * What was there stays for when the Quran comes back.
 */
export function QuranOff({ title }: { title: string }) {
  const t = useTranslations('quran');
  return (
    <div>
      <Heading title={title} />
      <div data-quran-off className="appear rounded-card border border-dashed border-line bg-white/60 p-8 text-center">
        <span className="mx-auto grid size-12 place-items-center rounded-full bg-brand-soft text-brand-strong" aria-hidden="true">
          <Icon name="book" />
        </span>
        <p className="mt-3 font-semibold">{t('off.title')}</p>
        <p className="mt-1 text-sm text-muted">{t('off.lead')}</p>
      </div>
    </div>
  );
}

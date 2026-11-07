import { useTranslations } from 'next-intl';
import { AreaOff } from '@/components/area-off';

/**
 * A Quran page while the academy's plan leaves the Quran out (the API's 403
 * feature-not-in-plan): a calm word in place of the page, never an error.
 * What was there stays for when the Quran comes back. Every area's page off
 * says it the same way (`components/area-off.tsx`); the Quran in its own words.
 */
export function QuranOff({ title }: { title: string }) {
  const t = useTranslations('quran');
  return (
    <div data-quran-off>
      <AreaOff title={title} by="plan" icon="book" words={{ title: t('off.title'), lead: t('off.lead') }} />
    </div>
  );
}

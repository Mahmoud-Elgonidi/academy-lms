import { getTranslations } from 'next-intl/server';
import { CardEditor, type MyCard } from '@/components/teach/card-editor';
import { Heading } from '@/components/ui';
import { AreaOff } from '@/components/area-off';
import { areaGet } from '@/lib/modules-server';

/**
 * "My card on the academy's page": the teacher writes what families see
 * about them there and switches it on or off, with a preview beside the
 * form. Whether the academy took it off is said, never why. While the
 * academy has its page off, a calm word.
 */
export default async function Card() {
  const [t, found] = await Promise.all([getTranslations('teachCard'), areaGet<{ data: MyCard }>('/v1/teacher/card')]);
  if (found.off) return <AreaOff title={t('title')} by={found.by} icon="star" home="/teach" />;
  const mine = found.data;
  return (
    <div>
      <Heading title={t('title')} hint={t('lead')} />
      <CardEditor card={mine.data} />
    </div>
  );
}

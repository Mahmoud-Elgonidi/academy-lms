import { getTranslations } from 'next-intl/server';
import { CardEditor, type MyCard } from '@/components/teach/card-editor';
import { Heading } from '@/components/ui';
import { get } from '@/lib/api/server';

/**
 * "My card on the academy's page": the teacher writes what families see
 * about them there and switches it on or off, with a preview beside the
 * form. Whether the academy took it off is said, never why.
 */
export default async function Card() {
  const [t, mine] = await Promise.all([getTranslations('teachCard'), get<{ data: MyCard }>('/v1/teacher/card')]);
  return (
    <div>
      <Heading title={t('title')} hint={t('lead')} />
      <CardEditor card={mine.data} />
    </div>
  );
}

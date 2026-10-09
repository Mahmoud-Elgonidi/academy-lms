import { getTranslations } from 'next-intl/server';
import { notFound } from 'next/navigation';
import { MyDataView } from '@/components/my-data';
import { Heading } from '@/components/ui';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import { ownsData } from '@/lib/family/rules';
import type { MyData } from '@/lib/privacy';

/** My data in the family's area: adults only (the classic look); a younger student's data is their guardian's to ask about. */
export default async function FamilyMyData() {
  const fam = await family();
  if (!ownsData(fam.stage)) notFound();
  const [t, mine] = await Promise.all([getTranslations('myData'), get<{ data: MyData }>('/v1/me/data-requests')]);
  return (
    <div>
      <Heading title={t('title')} hint={t('lead')} />
      <MyDataView mine={mine.data} />
    </div>
  );
}

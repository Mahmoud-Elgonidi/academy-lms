import { getLocale, getTranslations } from 'next-intl/server';
import { MyDataView } from '@/components/my-data';
import { Heading } from '@/components/ui';
import { get } from '@/lib/api/server';
import type { MyData } from '@/lib/privacy';

/** A teacher's own data: a copy of what the academy keeps, and erasure of the account. */
export default async function TeachMyData() {
  const [t, locale, mine] = await Promise.all([getTranslations('myData'), getLocale(), get<{ data: MyData }>('/v1/me/data-requests')]);
  return (
    <div>
      <Heading title={t('title')} hint={t('leadTeacher')} />
      <MyDataView mine={mine.data} account={`/${locale}/teach/account`} />
    </div>
  );
}

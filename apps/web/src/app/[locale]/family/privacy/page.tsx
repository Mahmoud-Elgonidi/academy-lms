import { getTranslations } from 'next-intl/server';
import { ConsentCard } from '@/components/family/consent';
import { Empty, Heading } from '@/components/ui';
import { get } from '@/lib/api/server';
import type { Consents } from '@/lib/family/types';

/** A guardian's consent to each child's account: read and agree, or take it back. Live through LiveRefresh (consents.changed). */
export default async function Privacy() {
  const [t, consents] = await Promise.all([getTranslations('privacy'), get<Consents>('/v1/family/consents')]);
  return (
    <div>
      <Heading title={t('title')} hint={t('lead', { age: consents.age })} />
      {consents.children.length === 0 ? (
        <Empty>{t('none')}</Empty>
      ) : (
        <div className="space-y-4">
          {consents.children.map((child) => (
            <ConsentCard key={child.student.id} child={child} />
          ))}
        </div>
      )}
    </div>
  );
}

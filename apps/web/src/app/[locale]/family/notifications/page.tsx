import { getTranslations } from 'next-intl/server';
import { Inbox } from '@/components/inbox';
import { Empty, Heading } from '@/components/ui';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import { seesMoney } from '@/lib/family/rules';
import { familyTargetOf } from '@/lib/family/targets';
import type { Notification } from '@/lib/teach/types';

/** The person's messages from the academy, newest first; each opens where it is about. */
export default async function Notifications() {
  const [t, fam, inbox] = await Promise.all([
    getTranslations('family'),
    family(),
    get<{ data: Notification[]; meta: { unread: number } }>('/v1/notifications'),
  ]);
  const title = (await getTranslations(`nav.${fam.stage}`))('notifications');
  const items = inbox.data.map((n) => ({ ...n, href: familyTargetOf(n.action?.screen, seesMoney(fam.stage), n.action?.id) }));
  return (
    <div>
      <Heading title={title} hint={inbox.meta.unread > 0 ? t('unread', { n: inbox.meta.unread }) : undefined} />
      {items.length === 0 ? <Empty>{t('noNotifications')}</Empty> : <Inbox items={items} unread={inbox.meta.unread} />}
    </div>
  );
}

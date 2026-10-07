import { getTranslations } from 'next-intl/server';
import { Inbox } from '@/components/teach/inbox';
import { Empty, Heading } from '@/components/ui';
import { get } from '@/lib/api/server';
import type { Notification } from '@/lib/teach/types';
import { targetOf } from '@/lib/teach/targets';

/** The teacher's messages from the academy, newest first; each opens where it is about. */
export default async function Notifications() {
  const [t, nav, inbox] = await Promise.all([
    getTranslations('teach'),
    getTranslations('nav'),
    get<{ data: Notification[]; meta: { unread: number } }>('/v1/notifications'),
  ]);
  const items = inbox.data.map((n) => ({ ...n, href: targetOf(n.action?.screen, n.action?.id) }));
  return (
    <div>
      <Heading title={nav('notifications')} hint={inbox.meta.unread > 0 ? t('unread', { n: inbox.meta.unread }) : undefined} />
      {items.length === 0 ? <Empty>{t('noNotifications')}</Empty> : <Inbox items={items} unread={inbox.meta.unread} />}
    </div>
  );
}

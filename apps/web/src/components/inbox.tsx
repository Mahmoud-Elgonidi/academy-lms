'use client';

import { useTranslations } from 'next-intl';
import { useTransition } from 'react';
import { markRead } from '@/lib/actions/shared';
import { useRouter } from '@/i18n/navigation';
import { Button, join } from '@/components/ui';
import type { Notification } from '@/lib/teach/types';

/** Messages, in full with their line breaks: opening one marks it read and goes where it is about (an announcement stays here). */
export function Inbox({ items, unread }: { items: (Notification & { href: string })[]; unread: number }) {
  const t = useTranslations('common');
  const router = useRouter();
  const [pending, start] = useTransition();
  return (
    <div>
      {unread > 0 ? (
        <div className="mb-4 flex justify-end">
          <Button variant="quiet" className="min-h-10 px-4 text-sm" disabled={pending} onClick={() => start(async () => void (await markRead()))}>
            {t('markAllRead')}
          </Button>
        </div>
      ) : null}
      <ul className="stagger space-y-2">
        {items.map((n) => (
          <li key={n.id}>
            <button
              type="button"
              onClick={() =>
                start(async () => {
                  if (!n.read) await markRead([n.id]);
                  router.push(n.href);
                })
              }
              className={join(
                'lift flex w-full items-start gap-3 rounded-card border bg-white p-4 text-start',
                n.read ? 'border-line' : 'border-brand-line',
                n.urgent && !n.read && 'border-danger/40',
              )}
            >
              <span className={join('mt-2 size-2 shrink-0 rounded-full', n.read ? 'bg-transparent' : n.urgent ? 'bg-danger' : 'bg-brand')} aria-hidden="true" />
              <span className="min-w-0 flex-1">
                <span className={join('block', !n.read && 'font-semibold')}>{n.title}</span>
                <span className="mt-0.5 block whitespace-pre-line text-sm text-muted [overflow-wrap:anywhere]">{n.body}</span>
                <span className="mt-1 block text-xs text-muted">{n.created_at.label}</span>
              </span>
            </button>
          </li>
        ))}
      </ul>
    </div>
  );
}

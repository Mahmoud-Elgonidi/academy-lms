'use client';

import { useTranslations } from 'next-intl';
import { Link, usePathname } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { join } from '@/components/ui';
import { Unread } from '@/components/unread';
import { itemsOf } from '@/lib/family/nav-items';
import type { Stage } from '@/lib/family/types';

function useActive() {
  const path = usePathname();
  return (href: string) => (href === '/family' ? path === '/family' : path === href || path.startsWith(`${href}/`));
}

export function SideNav({ stage, money, guardian = false, unread }: { stage: Stage; money: boolean; guardian?: boolean; unread: number }) {
  const t = useTranslations(`nav.${stage}`);
  const common = useTranslations('common');
  const active = useActive();
  return (
    <nav aria-label={t('menu')}>
      <ul className="space-y-0.5">
        {itemsOf(stage, money, guardian).all.map((item) => (
          <li key={item.href}>
            <Link
              href={item.href}
              aria-current={active(item.href) ? 'page' : undefined}
              className={join(
                'flex min-h-11 items-center gap-3 rounded-control px-3 transition-colors',
                active(item.href) ? 'bg-brand-soft font-semibold text-brand-strong' : 'text-ink hover:bg-paper',
              )}
            >
              <Icon name={item.icon} className={active(item.href) ? 'text-brand' : 'text-muted'} />
              {t(item.key)}
              {item.key === 'notifications' ? <Unread n={unread} label={common('newMessages', { n: unread })} /> : null}
            </Link>
          </li>
        ))}
      </ul>
    </nav>
  );
}

/** The phone's tab bar; the kids' one is bigger, with room for small fingers. */
export function TabBar({ stage, money, unread }: { stage: Stage; money: boolean; unread: number }) {
  const t = useTranslations(`nav.${stage}`);
  const common = useTranslations('common');
  const active = useActive();
  const { tabs } = itemsOf(stage, money);
  const inMore = !tabs.slice(0, -1).some((tab) => active(tab.href));
  const kids = stage === 'kids';
  return (
    <nav aria-label={t('menu')} className="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden">
      <ul className="mx-auto grid max-w-lg" style={{ gridTemplateColumns: `repeat(${tabs.length}, minmax(0, 1fr))` }}>
        {tabs.map((tab) => {
          const on = tab.key === 'more' ? inMore : active(tab.href);
          return (
            <li key={tab.href}>
              <Link
                href={tab.href}
                aria-current={on ? 'page' : undefined}
                className={join(
                  'relative flex flex-col items-center justify-center gap-0.5',
                  kids ? 'min-h-16 text-xs' : 'min-h-14 text-[11px]',
                  on ? 'font-semibold text-brand-strong' : 'text-muted',
                )}
              >
                <span className={join('grid place-items-center rounded-full transition-colors', kids && 'size-9', kids && on && 'bg-brand-soft')}>
                  <Icon name={tab.icon} size={kids ? 24 : 22} className={on ? 'text-brand' : undefined} />
                </span>
                {t(tab.key)}
                {tab.key === 'more' ? <Unread n={unread} label={common('newMessages', { n: unread })} dot className={kids ? 'top-2.5 end-[calc(50%-1.1rem)]' : undefined} /> : null}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}

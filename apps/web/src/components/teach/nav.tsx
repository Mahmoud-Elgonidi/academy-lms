'use client';

import { useLocale, useTranslations } from 'next-intl';
import { Link, usePathname } from '@/i18n/navigation';
import { Icon, type IconName } from '@/components/icons';
import { join } from '@/components/ui';
import { Unread } from '@/components/unread';
import { inPlan } from '@/lib/teach/areas';

type Item = { href: string; key: string; icon: IconName };

export const TEACHING: Item[] = [
  { href: '/teach', key: 'today', icon: 'sun' },
  { href: '/teach/week', key: 'week', icon: 'calendar' },
  { href: '/teach/reports', key: 'reports', icon: 'report' },
  { href: '/teach/marking', key: 'marking', icon: 'mark' },
  { href: '/teach/students', key: 'students', icon: 'people' },
  { href: '/teach/trials', key: 'trials', icon: 'spark' },
];

export const ME: Item[] = [
  { href: '/teach/hours', key: 'hours', icon: 'clock' },
  { href: '/teach/time-off', key: 'timeOff', icon: 'leave' },
  { href: '/teach/earnings', key: 'earnings', icon: 'wallet' },
  { href: '/teach/performance', key: 'performance', icon: 'chart' },
  { href: '/teach/notifications', key: 'notifications', icon: 'bell' },
];

/** The phone's tab bar: the day's work, and the rest under "more". */
const TABS: Item[] = [TEACHING[0]!, TEACHING[1]!, TEACHING[2]!, TEACHING[4]!, { href: '/teach/more', key: 'menu', icon: 'menu' }];

function useActive() {
  const path = usePathname();
  return (href: string) => (href === '/teach' ? path === '/teach' : path === href || path.startsWith(`${href}/`));
}

/** The side menu on wide screens: the pages the academy's plan includes. */
export function SideNav({ unread, features }: { unread: number; features: readonly string[] }) {
  const t = useTranslations('nav');
  const common = useTranslations('common');
  const active = useActive();
  const group = (title: string, items: Item[]) => (
    <div>
      <p className="mb-1 px-3 text-xs font-semibold text-muted">{title}</p>
      <ul className="space-y-0.5">
        {items.filter((item) => inPlan(item.href, features)).map((item) => (
          <li key={item.href}>
            <Link
              href={item.href}
              aria-current={active(item.href) ? 'page' : undefined}
              className={join(
                'flex min-h-10 items-center gap-3 rounded-control px-3 text-[15px] transition-colors',
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
    </div>
  );
  return (
    <nav aria-label={t('menu')} className="space-y-6">
      {group(t('teaching'), TEACHING)}
      {group(t('me'), ME)}
    </nav>
  );
}

/** The tab bar at the bottom of a phone's screen. */
export function TabBar({ unread }: { unread: number }) {
  const t = useTranslations('nav');
  const common = useTranslations('common');
  const active = useActive();
  const inMore = ![...TABS.slice(0, 4)].some((tab) => active(tab.href));
  return (
    <nav aria-label={t('menu')} className="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden">
      <ul className="mx-auto grid max-w-lg grid-cols-5">
        {TABS.map((tab) => {
          const on = tab.key === 'menu' ? inMore : active(tab.href);
          return (
            <li key={tab.href}>
              <Link
                href={tab.href}
                aria-current={on ? 'page' : undefined}
                className={join('relative flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px]', on ? 'font-semibold text-brand-strong' : 'text-muted')}
              >
                <Icon name={tab.icon} size={22} className={on ? 'text-brand' : undefined} />
                {t(tab.key)}
                {tab.key === 'menu' ? <Unread n={unread} label={common('newMessages', { n: unread })} dot /> : null}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}

/** The same page in the other language. */
export function LanguageSwitch({ className }: { className?: string }) {
  const t = useTranslations('common');
  const path = usePathname();
  return (
    <Link href={path} locale={useLocale() === 'ar' ? 'en' : 'ar'} className={className}>
      <Icon name="globe" size={18} />
      {t('language')}
    </Link>
  );
}

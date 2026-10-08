import { getTranslations } from 'next-intl/server';
import { signOut } from '@/app/[locale]/sign-in/actions';
import { Link } from '@/i18n/navigation';
import { Icon, type IconName } from '@/components/icons';
import { LanguageSwitch } from '@/components/teach/nav';
import { Heading } from '@/components/ui';
import { Unread } from '@/components/unread';
import { liveOf } from '@/lib/live-server';
import { me } from '@/lib/viewer';

const ITEMS: { href: string; key: string; icon: IconName }[] = [
  { href: '/teach/marking', key: 'marking', icon: 'mark' },
  { href: '/teach/trials', key: 'trials', icon: 'spark' },
  { href: '/teach/hours', key: 'hours', icon: 'clock' },
  { href: '/teach/time-off', key: 'timeOff', icon: 'leave' },
  { href: '/teach/earnings', key: 'earnings', icon: 'wallet' },
  { href: '/teach/performance', key: 'performance', icon: 'chart' },
  { href: '/teach/notifications', key: 'notifications', icon: 'bell' },
];

/** On a phone: the teacher's other screens, the language, and signing out. */
export default async function More() {
  const [nav, common, person, { unread }] = await Promise.all([getTranslations('nav'), getTranslations('common'), me(), liveOf()]);
  const row = 'flex min-h-13 items-center gap-3 px-4 py-3';
  return (
    <div>
      <Heading title={nav('menu')} hint={person.name} />
      <ul className="divide-y divide-line overflow-hidden rounded-card border border-line bg-white">
        {ITEMS.map((item) => (
          <li key={item.href}>
            <Link href={item.href} className={`${row} hover:bg-paper`}>
              <Icon name={item.icon} className="text-muted" />
              <span className="flex-1">{nav(item.key)}</span>
              {item.key === 'notifications' ? <Unread n={unread} label={common('newMessages', { n: unread })} className="ms-0" /> : null}
              <Icon name="chevron" size={18} className="text-muted rtl:-scale-x-100" />
            </Link>
          </li>
        ))}
      </ul>
      <div className="mt-5 divide-y divide-line overflow-hidden rounded-card border border-line bg-white">
        <LanguageSwitch className={`${row} hover:bg-paper`} />
        <form action={signOut}>
          <button className={`${row} w-full text-danger hover:bg-danger-soft`}>
            <Icon name="out" className="rtl:-scale-x-100" />
            {common('signOut')}
          </button>
        </form>
      </div>
    </div>
  );
}

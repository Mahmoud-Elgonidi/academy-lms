import { getTranslations } from 'next-intl/server';
import { signOut } from '@/app/[locale]/sign-in/actions';
import { itemsOf } from '@/lib/family/nav-items';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { LanguageSwitch } from '@/components/teach/nav';
import { Heading } from '@/components/ui';
import { family } from '@/lib/family/context';
import { seesMoney } from '@/lib/family/rules';
import { me } from '@/lib/viewer';

/** On a phone: the places that are not tabs, the language, and signing out. */
export default async function More() {
  const [fam, person, common] = await Promise.all([family(), me(), getTranslations('common')]);
  const nav = await getTranslations(`nav.${fam.stage}`);
  const { all, tabs } = itemsOf(fam.stage, seesMoney(fam.stage));
  const rest = all.filter((item) => !tabs.some((tab) => tab.href === item.href));
  const row = 'flex min-h-13 items-center gap-3 px-4 py-3';
  return (
    <div>
      <Heading title={nav('more')} hint={person.name} />
      <ul className="divide-y divide-line overflow-hidden rounded-card border border-line bg-white">
        {rest.map((item) => (
          <li key={item.href}>
            <Link href={item.href} className={`${row} hover:bg-paper`}>
              <Icon name={item.icon} className="text-muted" />
              <span className="flex-1">{nav(item.key)}</span>
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

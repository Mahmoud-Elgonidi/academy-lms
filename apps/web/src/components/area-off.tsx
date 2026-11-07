import { useTranslations } from 'next-intl';
import { Icon, type IconName } from '@/components/icons';
import { Heading } from '@/components/ui';
import { Link } from '@/i18n/navigation';
import type { OffBy } from '@/lib/modules';

/**
 * A page whose area is off while someone reaches it directly (the API's 403
 * feature-not-in-plan, lib/modules.ts): a calm word in place of the page,
 * never an error. The academy turning it off says so; a plan without it says
 * that. What was there stays for when it comes back. `home` leads back.
 */
export function AreaOff({ title, by, icon = 'spark', home, words }: { title: string; by: OffBy; icon?: IconName; home?: string; words?: { title: string; lead: string } }) {
  const t = useTranslations('areas');
  const said = words ?? { title: t(`off.${by}.title`), lead: t(`off.${by}.lead`) };
  return (
    <div>
      <Heading title={title} />
      <div data-area-off={by} className="appear rounded-card border border-dashed border-line bg-white/60 p-8 text-center">
        <span className="mx-auto grid size-12 place-items-center rounded-full bg-brand-soft text-brand-strong" aria-hidden="true">
          <Icon name={icon} />
        </span>
        <p className="mt-3 font-semibold">{said.title}</p>
        <p className="mt-1 text-sm text-muted">{said.lead}</p>
        {home ? (
          <Link href={home} className="mt-4 inline-flex min-h-10 items-center rounded-control px-4 text-sm font-semibold text-brand-strong hover:bg-brand-soft">
            {t('home')}
          </Link>
        ) : null}
      </div>
    </div>
  );
}

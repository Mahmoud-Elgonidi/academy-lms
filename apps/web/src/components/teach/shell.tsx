import { cookies } from 'next/headers';
import { getTranslations } from 'next-intl/server';
import type { ReactNode } from 'react';
import { signOut } from '@/app/[locale]/sign-in/actions';
import { Icon } from '@/components/icons';
import { LiveRefresh } from '@/components/live-refresh';
import { TimeFormatProvider } from '@/components/time-format';
import { ZoneChip } from '@/components/zone-chip';
import { liveOf } from '@/lib/live-server';
import { CHOSEN_ZONE_COOKIE, TIMEZONE_COOKIE } from '@/lib/session';
import { timeFormatOf } from '@/lib/time';
import type { Branding, Me } from '@/lib/viewer';
import { LanguageSwitch, SideNav, TabBar } from './nav';

/** The teacher's frame: the academy and the person, the menu (with the unread count), and the page, kept live, its clocks written the person's way. */
export async function Shell({ person, look, locale, children }: { person: Me; look: Branding; locale: string; children: ReactNode }) {
  const [t, live, jar] = await Promise.all([getTranslations('common'), liveOf(), cookies()]);
  const academy = locale === 'ar' ? look.name.ar : look.name.en;
  return (
    <div className="min-h-dvh lg:grid lg:grid-cols-[16rem_1fr]">
      <LiveRefresh realtime={live.realtime} />
      <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:start-3 focus:top-3 focus:z-50 focus:rounded-control focus:bg-white focus:px-3 focus:py-2">
        {t('skip')}
      </a>
      <aside className="hidden border-e border-line bg-white lg:flex lg:h-dvh lg:flex-col lg:sticky lg:top-0">
        <div className="flex items-center gap-3 px-5 pb-4 pt-6">
          <Mark look={look} academy={academy} />
          <p className="font-heading text-[15px] font-bold leading-tight">{academy}</p>
        </div>
        <div className="flex-1 overflow-y-auto px-3 py-2">
          <SideNav unread={live.unread} chats={live.chats} features={person.plan_features ?? []} />
        </div>
        <div className="space-y-1 border-t border-line p-3">
          <p className="truncate px-3 pb-1 text-sm font-semibold">{person.name}</p>
          <LanguageSwitch className="flex min-h-10 items-center gap-3 rounded-control px-3 text-sm text-muted hover:bg-paper hover:text-ink" />
          <form action={signOut}>
            <button className="flex min-h-10 w-full items-center gap-3 rounded-control px-3 text-sm text-muted hover:bg-paper hover:text-ink">
              <Icon name="out" size={18} className="rtl:-scale-x-100" />
              {t('signOut')}
            </button>
          </form>
        </div>
      </aside>

      <div className="min-w-0">
        <header className="sticky top-0 z-20 flex items-center gap-3 border-b border-line bg-white/95 px-4 py-3 backdrop-blur lg:hidden">
          <Mark look={look} academy={academy} small />
          <p className="truncate font-heading font-bold">{academy}</p>
        </header>
        <main id="main" className="mx-auto w-full max-w-4xl px-4 pb-28 pt-5 sm:px-6 lg:px-10 lg:pb-12 lg:pt-10">
          <TimeFormatProvider format={timeFormatOf(person.time_format)}>{children}</TimeFormatProvider>
        </main>
      </div>
      <ZoneChip
        shown={person.display_timezone}
        shownLabel={person.timezone_label}
        account={person.timezone ?? null}
        academy={person.academy ? { zone: person.academy.default_timezone, label: person.academy.timezone_label } : null}
        mismatch={person.timezone_mismatch === true}
        chosen={jar.get(CHOSEN_ZONE_COOKIE)?.value ?? null}
        device={jar.get(TIMEZONE_COOKIE)?.value ?? null}
        format={timeFormatOf(person.time_format)}
      />
      <TabBar unread={live.unread} chats={live.chats} />
    </div>
  );
}

function Mark({ look, academy, small }: { look: Branding; academy: string; small?: boolean }) {
  const size = small ? 'size-8 text-sm' : 'size-10 text-base';
  if (look.logo_url) {
    // eslint-disable-next-line @next/next/no-img-element -- the academy's own logo, from its branding
    return <img src={look.logo_url} alt="" className={`${size} rounded-control object-contain`} />;
  }
  return (
    <span aria-hidden="true" className={`${size} grid shrink-0 place-items-center rounded-control bg-brand font-heading font-bold text-white`}>
      {academy.trim().charAt(0)}
    </span>
  );
}

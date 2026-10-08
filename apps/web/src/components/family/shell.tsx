import { getTranslations } from 'next-intl/server';
import type { ReactNode } from 'react';
import { signOut } from '@/app/[locale]/sign-in/actions';
import { Icon } from '@/components/icons';
import { LiveRefresh } from '@/components/live-refresh';
import { LanguageSwitch } from '@/components/teach/nav';
import type { Family } from '@/lib/family/context';
import { seesMoney } from '@/lib/family/rules';
import { liveOf } from '@/lib/live-server';
import type { Branding, Me } from '@/lib/viewer';
import { LearnerSwitch } from './learner-switch';
import { SideNav, TabBar } from './nav';

/**
 * The family's frame in the look of the person signed in: a student's own
 * stage (fonts, corners, tab bar) or the classic one, the academy's colours
 * either way. A guardian with several children picks whose pages they see.
 */
export async function FamilyShell({ person, look, locale, fam, fontClasses, children }: { person: Me; look: Branding; locale: string; fam: Family; fontClasses: string; children: ReactNode }) {
  const [t, live] = await Promise.all([getTranslations('common'), liveOf()]);
  const academy = locale === 'ar' ? look.name.ar : look.name.en;
  const money = seesMoney(fam.stage);
  return (
    <div data-stage={fam.stage} className={`${fontClasses} min-h-dvh lg:grid lg:grid-cols-[16rem_1fr]`}>
      <LiveRefresh realtime={live.realtime} />
      <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:start-3 focus:top-3 focus:z-50 focus:rounded-control focus:bg-white focus:px-3 focus:py-2">
        {t('skip')}
      </a>
      <aside className="hidden border-e border-line bg-white lg:sticky lg:top-0 lg:flex lg:h-dvh lg:flex-col">
        <div className="flex items-center gap-3 px-5 pb-4 pt-6">
          <Mark look={look} academy={academy} />
          <p className="font-heading text-[15px] font-bold leading-tight">{academy}</p>
        </div>
        <div className="flex-1 overflow-y-auto px-3 py-2">
          <SideNav stage={fam.stage} money={money} unread={live.unread} />
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
        <header className="sticky top-0 z-20 flex items-center gap-3 border-b border-line bg-white/95 px-4 py-3 backdrop-blur lg:static lg:border-0 lg:bg-transparent lg:px-10 lg:pt-6 lg:backdrop-blur-none">
          <span className="lg:hidden">
            <Mark look={look} academy={academy} small />
          </span>
          <p className="min-w-0 flex-1 truncate font-heading font-bold lg:hidden">{academy}</p>
          <span className="hidden flex-1 lg:block" />
          {fam.current ? <LearnerSwitch learners={fam.learners} current={fam.current.id} /> : null}
        </header>
        <main id="main" className="mx-auto w-full max-w-4xl px-4 pb-28 pt-5 sm:px-6 lg:px-10 lg:pb-12 lg:pt-4">
          {children}
        </main>
      </div>
      <TabBar stage={fam.stage} money={money} unread={live.unread} />
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

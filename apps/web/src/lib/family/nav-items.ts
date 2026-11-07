import type { IconName } from '@/components/icons';
import { hasModule, type Module } from '../modules';
import type { Stage } from './types';

/** A place in a look's menu; `also` are the other addresses it stands for (a student's home is their journey). */
export type NavItem = { href: string; key: string; icon: IconName; also?: string[] };

/**
 * Each look's places, named for its age (nav.{stage}.* in messages), as the
 * app's tabs, the same names everywhere: a student's home is their journey
 * (رحلتي: the curriculum as islands or levels, the missions, the stars, the
 * games on each lesson), then their lessons and, by age, their teachers'
 * messages (kids, middle school) or homework and reports (teens); guardians
 * and adults the classic set, with the journey (الرحلة) where progress was,
 * and a guardian their children's privacy (their consent to each child's
 * account). Lesson recordings are everyone's but a child's in the kids'
 * look. Everyone but a child in the kids' look can write to the academy
 * (help) and link Google or Apple to their account; adults in the classic
 * look see their data (a copy, erasure). Everyone, a child too, can tell the
 * academy's safeguarding leads that something is wrong (tell us), and talk
 * with the learner's teachers (messages; the guardians are in every
 * conversation). The roadmap and the teacher's reports stay a place of
 * their own for students, and open from the journey for everyone. While
 * the academy's plan has the Quran (`quran`), everyone has its page (the
 * plan, the hifz map, exams, certificates) after messages, so the tabs stay.
 * Everyone has their free trials (the teacher's reports after them) after
 * the notifications, never as a tab. Whoever pays (the classic look) starts
 * a new course from its own place after money.
 *
 * `modules` are the academy's areas that are on (`me.modules`, lib/modules.ts;
 * left out, everything is): messages are messaging's, help support's, the
 * journey the curriculum's, the Quran the Quran's. An area that is off leaves
 * the menu and the tabs, and the next place takes its tab (homework); a
 * student's home without the curriculum is no longer the journey.
 */
export function itemsOf(stage: Stage, money: boolean, guardian = false, quran = false, modules?: readonly string[] | null): { all: NavItem[]; tabs: NavItem[] } {
  const on = (area: Module) => (modules ? hasModule(modules, area) : true);
  const curriculum = on('curriculum');
  const lessons: NavItem = { href: '/family/lessons', key: 'lessons', icon: 'calendar' };
  const homework: NavItem = { href: '/family/homework', key: 'homework', icon: 'book' };
  const progress: NavItem = { href: '/family/progress', key: 'progress', icon: 'chart' };
  const journey: NavItem = { href: '/family/journey', key: 'journey', icon: 'map' };
  const inbox: NavItem = { href: '/family/notifications', key: 'notifications', icon: 'bell' };
  const messageItems: NavItem[] = on('messaging') ? [{ href: '/family/messages', key: 'messages', icon: 'chat' }] : [];
  // The journey (curriculum); without it a guardian's or an adult's courses and reports are their own place, as a student's are.
  const journeyItems: NavItem[] = curriculum ? [journey] : [progress];
  const more: NavItem = { href: '/family/more', key: 'more', icon: 'menu' };
  // The Quran (its page, and the hifz map under it): only while the academy's plan has it.
  const quranItems: NavItem[] = quran && on('quran') ? [{ href: '/family/quran', key: 'quran', icon: 'book', also: ['/family/hifz'] }] : [];
  // A student's home is their journey while the academy has its curriculum on.
  const home: NavItem = stage === 'classic' || !curriculum ? { href: '/family', key: 'home', icon: 'home' } : { href: '/family', key: 'home', icon: 'map', also: ['/family/journey'] };
  const pay: NavItem[] = money ? [{ href: '/family/money', key: 'money', icon: 'coins', also: ['/family/invoices'] }] : [];
  // Starting a new course and paying for it (docs/architecture/02, section 27, J3): whoever pays, in the classic look.
  const start: NavItem[] = money ? [{ href: '/family/start', key: 'start', icon: 'plus' }] : [];
  const privacy: NavItem[] = guardian ? [{ href: '/family/privacy', key: 'privacy', icon: 'shield' }] : [];
  // Lesson recordings (consent, and the ones to watch): everyone but a child in the kids' look (their guardian decides).
  const recordings: NavItem[] = stage === 'kids' ? [] : [{ href: '/family/recordings', key: 'recordings', icon: 'video' }];
  // Writing to the academy: everyone but a child in the kids' look (their guardian writes).
  const help: NavItem[] = stage === 'kids' || !on('support') ? [] : [{ href: '/family/help', key: 'help', icon: 'help' }];
  // My account (signing in with Google or Apple): everyone but a child in the kids' look.
  const account: NavItem[] = stage === 'kids' ? [] : [{ href: '/family/account', key: 'account', icon: 'lock' }];
  // Telling the academy's safeguarding leads that something is wrong: everyone, in their own words.
  const tellUs: NavItem = { href: '/family/tell-us', key: 'tellUs', icon: 'hand' };
  // The learner's free trials and their reports (docs/architecture/02, section 27, J2): everyone; prices, the offer and booking in the classic look alone.
  const trials: NavItem = { href: '/family/trials', key: 'trials', icon: 'spark' };
  // My data (a copy, erasure): adults in the classic look.
  const myData: NavItem = { href: '/family/my-data', key: 'myData', icon: 'file' };

  if (stage === 'classic') {
    return {
      all: [home, lessons, ...journeyItems, ...messageItems, ...quranItems, homework, ...pay, ...start, inbox, trials, ...recordings, ...privacy, ...help, tellUs, ...account, myData],
      tabs: curriculum ? [home, lessons, journey, ...(money ? pay : [homework]), more] : [home, lessons, homework, ...pay, more],
    };
  }
  if (stage === 'teen') {
    return { all: [home, lessons, homework, progress, ...messageItems, ...quranItems, inbox, trials, ...recordings, ...help, tellUs, ...account], tabs: [home, lessons, homework, progress, more] };
  }
  // Kids and middle school: their journey, lessons and teachers on the bar; homework (also the journey's missions) under more.
  return {
    all: [home, lessons, ...messageItems, ...quranItems, homework, progress, inbox, trials, ...recordings, ...help, tellUs, ...account],
    tabs: [home, lessons, ...(messageItems.length > 0 ? messageItems : [homework]), more],
  };
}

/** Whether a place is the page at this path (a student's home also on /family/journey). */
export function isAt(item: Pick<NavItem, 'href' | 'also'>, path: string): boolean {
  const under = (href: string) => path === href || path.startsWith(`${href}/`);
  return (item.href === '/family' ? path === '/family' : under(item.href)) || (item.also ?? []).some(under);
}

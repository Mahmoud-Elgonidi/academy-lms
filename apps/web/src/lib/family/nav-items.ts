import type { IconName } from '@/components/icons';
import type { Stage } from './types';

export type NavItem = { href: string; key: string; icon: IconName };

/**
 * Each look's places, named for its age (nav.{stage}.* in messages), as the
 * app's tabs: kids have their journey and missions, middle school its quests,
 * teens their plan and goals; guardians and adults the classic set, and a
 * guardian their children's privacy (their consent to each child's account).
 * Lesson recordings are everyone's but a child's in the kids' look.
 * Everyone but a child in the kids' look can write to the academy (help)
 * and link Google or Apple to their account; adults in the classic look
 * see their data (a copy, erasure). Everyone, a child too, can tell the
 * academy's safeguarding leads that something is wrong (tell us), and
 * talk with the learner's teachers (messages; the guardians are in every
 * conversation). Games practise the learner's own lessons: a tab of their
 * own for kids (the islands) and middle school, in the menu for the rest
 * (homework then goes to "more" for those two, as in the app).
 */
export function itemsOf(stage: Stage, money: boolean, guardian = false): { all: NavItem[]; tabs: NavItem[] } {
  const lessons: NavItem = { href: '/family/lessons', key: 'lessons', icon: 'calendar' };
  const homework: NavItem = { href: '/family/homework', key: 'homework', icon: 'book' };
  const progress: NavItem = { href: '/family/progress', key: 'progress', icon: 'chart' };
  const challenges: NavItem = { href: '/family/challenges', key: 'challenges', icon: 'trophy' };
  const inbox: NavItem = { href: '/family/notifications', key: 'notifications', icon: 'bell' };
  const messages: NavItem = { href: '/family/messages', key: 'messages', icon: 'chat' };
  const more: NavItem = { href: '/family/more', key: 'more', icon: 'menu' };
  const games: NavItem = { href: '/family/games', key: 'games', icon: stage === 'kids' ? 'boat' : 'gamepad' };
  const home: NavItem = { href: '/family', key: 'home', icon: stage === 'kids' ? 'map' : stage === 'teen' ? 'flag' : 'home' };
  const pay: NavItem[] = money ? [{ href: '/family/money', key: 'money', icon: 'coins' }] : [];
  const privacy: NavItem[] = guardian ? [{ href: '/family/privacy', key: 'privacy', icon: 'shield' }] : [];
  // Lesson recordings (consent, and the ones to watch): everyone but a child in the kids' look (their guardian decides).
  const recordings: NavItem[] = stage === 'kids' ? [] : [{ href: '/family/recordings', key: 'recordings', icon: 'video' }];
  // Writing to the academy: everyone but a child in the kids' look (their guardian writes).
  const help: NavItem[] = stage === 'kids' ? [] : [{ href: '/family/help', key: 'help', icon: 'help' }];
  // My account (signing in with Google or Apple): everyone but a child in the kids' look.
  const account: NavItem[] = stage === 'kids' ? [] : [{ href: '/family/account', key: 'account', icon: 'lock' }];
  // Telling the academy's safeguarding leads that something is wrong: everyone, in their own words.
  const tellUs: NavItem = { href: '/family/tell-us', key: 'tellUs', icon: 'hand' };
  // My data (a copy, erasure): adults in the classic look.
  const myData: NavItem = { href: '/family/my-data', key: 'myData', icon: 'file' };

  if (stage === 'classic') {
    return {
      all: [home, lessons, messages, progress, homework, ...pay, challenges, games, inbox, ...recordings, ...privacy, ...help, tellUs, ...account, myData],
      tabs: [home, lessons, progress, ...(money ? pay : [homework]), more],
    };
  }
  const all = [home, games, challenges, lessons, messages, homework, progress, inbox, ...recordings, ...help, tellUs, ...account];
  if (stage === 'teen') return { all, tabs: [home, challenges, lessons, homework, more] };
  return { all, tabs: [home, games, challenges, lessons, more] };
}

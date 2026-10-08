import type { IconName } from '@/components/icons';
import type { Stage } from './types';

export type NavItem = { href: string; key: string; icon: IconName };

/**
 * Each look's places, named for its age (nav.{stage}.* in messages), as the
 * app's tabs: kids have their journey and missions, middle school its quests,
 * teens their plan and goals; guardians and adults the classic set, and a
 * guardian their children's privacy (their consent to each child's account).
 */
export function itemsOf(stage: Stage, money: boolean, guardian = false): { all: NavItem[]; tabs: NavItem[] } {
  const lessons: NavItem = { href: '/family/lessons', key: 'lessons', icon: 'calendar' };
  const homework: NavItem = { href: '/family/homework', key: 'homework', icon: 'book' };
  const progress: NavItem = { href: '/family/progress', key: 'progress', icon: 'chart' };
  const challenges: NavItem = { href: '/family/challenges', key: 'challenges', icon: 'trophy' };
  const inbox: NavItem = { href: '/family/notifications', key: 'notifications', icon: 'bell' };
  const more: NavItem = { href: '/family/more', key: 'more', icon: 'menu' };
  const home: NavItem = { href: '/family', key: 'home', icon: stage === 'kids' ? 'map' : stage === 'teen' ? 'flag' : 'home' };
  const pay: NavItem[] = money ? [{ href: '/family/money', key: 'money', icon: 'coins' }] : [];
  const privacy: NavItem[] = guardian ? [{ href: '/family/privacy', key: 'privacy', icon: 'shield' }] : [];

  if (stage === 'classic') {
    return {
      all: [home, lessons, progress, homework, ...pay, challenges, inbox, ...privacy],
      tabs: [home, lessons, progress, ...(money ? pay : [homework]), more],
    };
  }
  return { all: [home, challenges, lessons, homework, progress, inbox], tabs: [home, challenges, lessons, homework, more] };
}

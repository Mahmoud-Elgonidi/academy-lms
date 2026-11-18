import { itemsOf } from '../src/lib/family/nav-items';
import type { Stage } from '../src/lib/family/types';

/** Every page of the teacher's portal (TEACHING and ME in components/teach/nav.tsx, and the menu), under /{locale}/teach. */
export const TEACHER_PAGES = ['', '/week', '/reports', '/marking', '/students', '/messages', '/trials', '/hours', '/time-off', '/earnings', '/performance', '/recordings', '/notifications', '/help', '/concern', '/documents', '/card', '/account', '/my-data', '/more'];

/** Every place in a look's menu (lib/family/nav-items), the menu itself and the album, under /{locale}. */
export function familyPages(stage: Stage, { money = false, guardian = false } = {}): string[] {
  return [...new Set([...itemsOf(stage, money, guardian).all.map((item) => item.href), '/family/more', '/family/games/album'])];
}

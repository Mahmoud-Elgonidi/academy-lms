import { describe, expect, it } from 'vitest';
import { itemsOf } from './nav-items';
import { isGuardian, lessonActionsFor, seesMoney } from './rules';
import { familyTargetOf } from './targets';
import { STAGES } from './types';

const lesson = (status: string, can_cancel = false, can_refuse_substitute = false) => ({ status, can_cancel, can_refuse_substitute });

describe('what a family may do with a lesson', () => {
  it('gives a kid nothing to do, whatever the API allows', () => {
    expect(lessonActionsFor(lesson('scheduled', true, true), 'kids')).toBeNull();
    expect(lessonActionsFor(lesson('completed'), 'kids')).toBeNull();
  });

  it('offers cancelling and turning down a substitute only when the API allows them', () => {
    expect(lessonActionsFor(lesson('scheduled', true), 'classic')).toEqual({ cancel: true, refuse: false, rate: false, dispute: false });
    expect(lessonActionsFor(lesson('scheduled', false, true), 'teen')).toEqual({ cancel: false, refuse: true, rate: false, dispute: false });
    expect(lessonActionsFor(lesson('scheduled'), 'middle')).toBeNull();
  });

  it('offers rating once the lesson took place', () => {
    expect(lessonActionsFor(lesson('completed'), 'classic')?.rate).toBe(true);
    expect(lessonActionsFor(lesson('student_absent'), 'teen')?.rate).toBe(true);
    expect(lessonActionsFor(lesson('cancelled'), 'classic')).toBeNull();
  });

  it('offers a dispute only where the API does, never in the kids look', () => {
    const decided = { status: 'completed', can_cancel: false, can_refuse_substitute: false, can_dispute: true };
    expect(lessonActionsFor(decided, 'classic')).toEqual({ cancel: false, refuse: false, rate: true, dispute: true });
    expect(lessonActionsFor({ ...decided, status: 'teacher_absent' }, 'teen')).toEqual({ cancel: false, refuse: false, rate: false, dispute: true });
    expect(lessonActionsFor(decided, 'kids')).toBeNull();
  });
});

describe('the places of each look', () => {
  it('shows money only in the classic look', () => {
    expect(STAGES.filter(seesMoney)).toEqual(['classic']);
    for (const stage of ['kids', 'middle', 'teen'] as const) {
      const { all, tabs } = itemsOf(stage, seesMoney(stage));
      expect([...all, ...tabs].some((i) => i.href === '/family/money')).toBe(false);
    }
    expect(itemsOf('classic', true).tabs.map((i) => i.key)).toContain('money');
  });

  it('keeps five tabs, the last one "more", and every place in the menu', () => {
    for (const stage of STAGES) {
      for (const money of [true, false]) {
        const { all, tabs } = itemsOf(stage, money);
        expect(tabs).toHaveLength(5);
        expect(tabs.at(-1)?.key).toBe('more');
        for (const tab of tabs.slice(0, -1)) expect(all).toContainEqual(tab);
      }
    }
  });

  it('puts the challenges second for students and homework in the menu for a guardian who pays nothing', () => {
    expect(itemsOf('kids', false).tabs[1]?.key).toBe('challenges');
    expect(itemsOf('classic', false).tabs.map((i) => i.key)).toEqual(['home', 'lessons', 'progress', 'homework', 'more']);
  });
});

describe('lesson recordings', () => {
  it('are in every look but the kids\', where the guardian decides', () => {
    expect(itemsOf('kids', false).all.some((i) => i.key === 'recordings')).toBe(false);
    for (const stage of ['middle', 'teen', 'classic'] as const) {
      expect(itemsOf(stage, seesMoney(stage)).all.find((i) => i.key === 'recordings')?.href).toBe('/family/recordings');
    }
  });
});

describe('where a notification opens', () => {
  it('follows the screen the API names, as the app does', () => {
    expect(familyTargetOf('session', true)).toBe('/family/lessons');
    expect(familyTargetOf('lesson_rating', false)).toBe('/family/lessons');
    expect(familyTargetOf('report', false)).toBe('/family/progress');
    expect(familyTargetOf('homework', false)).toBe('/family/homework');
    expect(familyTargetOf('challenges', false)).toBe('/family/challenges');
  });

  it('opens an invoice only for whoever sees money, and anything else at home', () => {
    expect(familyTargetOf('invoice', true)).toBe('/family/money');
    expect(familyTargetOf('invoice', false)).toBe('/family');
    expect(familyTargetOf('write_report', true)).toBe('/family');
    expect(familyTargetOf(null, true)).toBe('/family');
    expect(familyTargetOf('guardian_consent', true)).toBe('/family/privacy');
  });
});

describe("a guardian's children's privacy", () => {
  it('is a place of the classic look for guardians only, never a tab', () => {
    expect(itemsOf('classic', true, true).all.map((i) => i.key)).toContain('privacy');
    expect(itemsOf('classic', true, true).tabs.map((i) => i.key)).not.toContain('privacy');
  });

  it('lets everyone but a child in the kids\' look write to the academy', () => {
    for (const stage of STAGES) {
      expect(itemsOf(stage, seesMoney(stage)).all.some((i) => i.href === '/family/help')).toBe(stage !== 'kids');
    }
    expect(itemsOf('classic', true).all.map((i) => i.key)).not.toContain('privacy');
    expect(isGuardian([{ is_self: true }])).toBe(false);
    expect(isGuardian([{ is_self: false }])).toBe(true);
  });
});

describe('telling the academy something is wrong', () => {
  it('is in every look, a child\'s too, and opens from its notification', () => {
    for (const stage of STAGES) {
      expect(itemsOf(stage, seesMoney(stage)).all.find((i) => i.key === 'tellUs')?.href).toBe('/family/tell-us');
    }
    expect(familyTargetOf('my_concerns', false)).toBe('/family/tell-us');
  });
});

describe('messages with the teachers', () => {
  it('are in every look, a child\'s too, and a notification opens the conversation', () => {
    for (const stage of STAGES) {
      expect(itemsOf(stage, seesMoney(stage)).all.find((i) => i.key === 'messages')?.href).toBe('/family/messages');
    }
    expect(familyTargetOf('conversation', false, 'c1')).toBe('/family/messages/c1');
    expect(familyTargetOf('conversation', true)).toBe('/family/messages');
  });
});

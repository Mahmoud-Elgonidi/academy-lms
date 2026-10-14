import { describe, expect, it } from 'vitest';
import { courseIsOn, courseState } from './course-state';
import { STAGES } from './types';

// A day on the academy's calendar (Cairo), as the API writes it: its midnight there.
const day = (date: string) => ({ utc: `${date}T00:00:00Z`, local: `${date}T00:00:00+03:00`, timezone: 'Africa/Cairo', label: date });

describe('a course paused or ended, as the family reads it', () => {
  it('says nothing of a course that simply goes on', () => {
    expect(courseState({ status: 'active', paused_from: null, paused_until: null, ends_on: null }, 'ar')).toBeNull();
    expect(courseState({ status: 'pending_payment' }, 'en')).toBeNull();
    expect(courseIsOn({ status: 'active' })).toBe(true);
  });

  it('says until which day a course is paused, the academy\'s day as written, whatever the viewer\'s zone', () => {
    const paused = { status: 'paused', paused_from: day('2026-10-13'), paused_until: day('2026-10-24'), ends_on: null };
    expect(courseState(paused, 'en')).toEqual({ key: 'paused', tone: 'info', values: { day: 'Saturday 24 October' } });
    expect(courseState(paused, 'ar')?.values.day).toBe('السبت، 24 أكتوبر');
    expect(courseIsOn(paused)).toBe(false);
  });

  it('speaks of a break to a child in the kids\' look, and of a pause to everyone else', () => {
    const paused = { status: 'paused', paused_from: day('2026-10-13'), paused_until: day('2026-10-24') };
    for (const stage of STAGES) expect(courseState(paused, 'en', stage)?.key).toBe(stage === 'kids' ? 'pausedKids' : 'paused');
  });

  it('tells a pause ahead from one under way, and an end from a course over', () => {
    expect(courseState({ status: 'active', paused_from: day('2026-11-03'), paused_until: day('2026-11-07') }, 'en'))
      .toEqual({ key: 'pauseAhead', tone: 'info', values: { from: 'Tuesday 3 November', until: 'Saturday 7 November' } });
    expect(courseState({ status: 'active', ends_on: day('2026-11-01') }, 'en')).toEqual({ key: 'ending', tone: 'attention', values: { day: 'Sunday 1 November' } });
    expect(courseState({ status: 'ended', ends_on: day('2026-11-01') }, 'en')).toEqual({ key: 'ended', tone: 'muted', values: {} });
    expect(courseIsOn({ status: 'ended' })).toBe(false);
  });
});

import { describe, expect, it } from 'vitest';
import { countdownUnits, formatMinor, hasPlan, itemsOf, nextTick, offerStateAt, perLessonMinor, timeLeft, trialBadge, urgency, type ReportOffer } from './report';

const at = (iso: string) => Date.parse(iso);
const offer = (ends: string | null): ReportOffer => ({
  percent: 10,
  ends_at: ends ? { utc: ends, local: ends, timezone: 'Africa/Cairo', label: 'الأربعاء 4 نوفمبر · 7:22 م' } : null,
  price: { price: '1,980 ج.م.', amount_minor: 198000, currency: 'EGP' },
  saving: { price: '220 ج.م.', amount_minor: 22000, currency: 'EGP' },
  family_discount_bigger: false,
});

describe('the offer as it stands', () => {
  it('keeps the API’s word until the end it gave passes on this clock', () => {
    const open = offer('2026-11-04T17:22:53Z');
    expect(offerStateAt('open', open, at('2026-11-04T17:22:52Z'))).toBe('open');
    expect(offerStateAt('open', open, at('2026-11-04T17:22:53Z'))).toBe('ended');
    expect(offerStateAt('open', open, at('2026-11-05T00:00:00Z'))).toBe('ended');
  });

  it('never opens one the API did not, and an open state without an offer is none', () => {
    for (const state of ['ended', 'used', 'started', 'none'] as const) {
      expect(offerStateAt(state, offer('2030-01-01T00:00:00Z'), at('2026-11-01T00:00:00Z'))).toBe(state);
    }
    expect(offerStateAt('open', null, at('2026-11-01T00:00:00Z'))).toBe('none');
    // An open offer with no end the page can read stays open (the server said so).
    expect(offerStateAt('open', offer(null), at('2026-11-01T00:00:00Z'))).toBe('open');
  });
});

describe('the countdown', () => {
  it('splits what is left into days, hours, minutes and seconds, never below nothing', () => {
    const left = timeLeft('2026-11-04T17:22:53Z', at('2026-11-02T15:10:03Z'));
    expect(left).toMatchObject({ days: 2, hours: 2, minutes: 12, seconds: 50, over: false });
    expect(timeLeft('2026-11-04T17:22:53Z', at('2026-11-05T00:00:00Z'))).toMatchObject({ ms: 0, days: 0, hours: 0, minutes: 0, seconds: 0, over: true });
    expect(timeLeft('not a time', at('2026-11-05T00:00:00Z')).over).toBe(true);
  });

  it('shows days, hours and minutes while a day is left, then hours, minutes and seconds', () => {
    const far = timeLeft('2026-11-04T17:22:53Z', at('2026-11-02T15:10:03Z'));
    expect(countdownUnits(far)).toEqual([{ unit: 'days', value: 2 }, { unit: 'hours', value: 2 }, { unit: 'minutes', value: 12 }]);
    const near = timeLeft('2026-11-04T17:22:53Z', at('2026-11-04T12:00:00Z'));
    expect(countdownUnits(near)).toEqual([{ unit: 'hours', value: 5 }, { unit: 'minutes', value: 22 }, { unit: 'seconds', value: 53 }]);
  });

  it('grows urgent in the last day and the last hour', () => {
    const end = '2026-11-04T17:00:00Z';
    expect(urgency(timeLeft(end, at('2026-11-03T16:59:00Z')))).toBe('calm');
    expect(urgency(timeLeft(end, at('2026-11-03T17:00:01Z')))).toBe('soon');
    expect(urgency(timeLeft(end, at('2026-11-04T16:00:01Z')))).toBe('last');
  });

  it('ticks on the next minute while days are shown, each second in the last day, and stops at the end', () => {
    const end = '2026-11-04T17:00:00Z';
    expect(nextTick(timeLeft(end, at('2026-11-02T16:59:30.250Z')))).toBe(29_750);
    expect(nextTick(timeLeft(end, at('2026-11-04T16:00:00.400Z')))).toBe(600);
    expect(nextTick(timeLeft(end, at('2026-11-04T16:00:00.000Z')))).toBe(1000);
    expect(nextTick(timeLeft(end, at('2026-11-04T17:00:00Z')))).toBe(0);
  });
});

describe('the report’s words and prices', () => {
  it('reads the teacher’s lines as items, without their bullets', () => {
    expect(itemsOf('تقرأ بصوت واضح.\n- تحفظ بسرعة\n\n2. تسأل حين لا تفهم\n• تركّز')).toEqual(['تقرأ بصوت واضح.', 'تحفظ بسرعة', 'تسأل حين لا تفهم', 'تركّز']);
    expect(itemsOf(null)).toEqual([]);
    expect(itemsOf('  \n ')).toEqual([]);
  });

  it('works out one lesson’s share of a block in whole minor units', () => {
    expect(perLessonMinor({ amount_minor: 220000 }, 8)).toBe(27500);
    expect(perLessonMinor({ amount_minor: 100 }, 3)).toBe(33);
    expect(perLessonMinor({ amount_minor: 100 }, 0)).toBeNull();
  });

  it('writes money with the currency’s decimals only when there are any, in Latin digits', () => {
    const plain = (text: string) => text.replace(/\s/gu, ' ');
    expect(plain(formatMinor(27500, 'EGP', 'en'))).toBe('EGP 275');
    expect(plain(formatMinor(24750, 'EGP', 'en'))).toBe('EGP 247.50');
    expect(plain(formatMinor(27500, 'KWD', 'en'))).toBe('KWD 27.500');
    expect(formatMinor(27500, 'SAR', 'ar')).toMatch(/275/);
    expect(formatMinor(27500, 'SAR', 'ar')).not.toMatch(/[٠-٩]/);
  });

  it('starts with a plan only while the teacher named one the academy still offers', () => {
    const plan = { id: 'p', name: 'x', format: 'private' as const, session_minutes: 30, sessions_per_week: 2, sessions_per_block: 8, validity_days: 42 };
    const rec = { plan, plan_withdrawn: false, per_week: 2, price: null, region: null };
    expect(hasPlan({ recommendation: rec })).toBe(true);
    expect(hasPlan({ recommendation: { ...rec, plan: null, plan_withdrawn: true } })).toBe(false);
    expect(hasPlan({ recommendation: { ...rec, plan: null } })).toBe(false);
  });

  it('names each trial on the family’s list by how it went', () => {
    expect(trialBadge({ status: 'attended', outcome: null, report_ready: true })).toBe('report');
    expect(trialBadge({ status: 'attended', outcome: null, report_ready: false })).toBe('waiting');
    expect(trialBadge({ status: 'booked', outcome: null, report_ready: false })).toBe('booked');
    expect(trialBadge({ status: 'missed', outcome: 'student_absent', report_ready: false })).toBe('missed');
    expect(trialBadge({ status: 'missed', outcome: 'teacher_absent', report_ready: false })).toBe('teacherMissed');
    expect(trialBadge({ status: 'cancelled', outcome: 'academy', report_ready: false })).toBe('academyCancelled');
    expect(trialBadge({ status: 'cancelled', outcome: 'family', report_ready: false })).toBe('cancelled');
  });
});

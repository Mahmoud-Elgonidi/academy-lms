import { describe, expect, it } from 'vitest';
import { balanceBar, balanceLook, balanceMoments, balanceRows, balanceState, dueSoon, kidsDots, payHref } from './balance';
import type { CourseBalance } from './types';

const at = (utc: string) => ({ utc, local: utc, timezone: 'Asia/Riyadh', label: `label ${utc}` });
const NOW = Date.parse('2026-11-02T12:00:00Z');

/** A classic reader's course, its plan of 8 lessons renewing at 2, as the API answers it. */
function course(over: Partial<CourseBalance> = {}): CourseBalance {
  return {
    enrollment_id: 'e1',
    status: 'active',
    viewer_stage: 'classic',
    lessons_left: 5,
    lessons_text: 'بقيت 5 حصص',
    makeup_lessons: 0,
    minutes_left: 150,
    plan: { id: 'p1', name: '8 حصص', format: 'private', shared: false, session_minutes: 30, sessions_per_week: 2, sessions_per_block: 8, renews_at_lessons_left: 2 },
    expiry: null,
    paused_until: null,
    ends_on: null,
    is_payer: true,
    renews: true,
    waiting_invoice: null,
    next_invoice: null,
    renewal_note: null,
    auto_renew: null,
    ...over,
  };
}

const waiting = (due: string | null, id: string | null = 'inv-1') => ({
  id, number: 'NR-1', outstanding: '940 ر.س.', outstanding_minor: 94000, currency: 'SAR', due_at: due ? at(due) : null, text: 'فاتورة الباقة التالية تنتظر الدفع',
});

describe('a course balance', () => {
  it('says how the course stands, the first that fits', () => {
    expect(balanceState(course())).toBe('calm');
    expect(balanceState(course({ lessons_left: 2 }))).toBe('low');
    expect(balanceState(course({ waiting_invoice: waiting('2026-11-03T15:00:00Z') }))).toBe('low');
    expect(balanceState(course({ lessons_left: 0 }))).toBe('empty');
    expect(balanceState(course({ ends_on: at('2026-12-01T00:00:00Z') }))).toBe('ending');
    expect(balanceState(course({ status: 'paused', lessons_left: 0 }))).toBe('paused');
    expect(balanceState(course({ status: 'ended' }))).toBe('ended');
    expect(balanceState(course({ status: 'pending_payment', lessons_left: 0 }))).toBe('first_payment');
    // The kids' look has no plan: lessons alone never read as low.
    expect(balanceState(course({ viewer_stage: 'kids', plan: null, lessons_left: 1 }))).toBe('calm');
  });

  it('draws the lessons left out of the package, with the renewal mark on it', () => {
    expect(balanceBar(course())).toEqual({ left: 5, total: 8, fill: 5 / 8, mark: 2 / 8, markAt: 2 });
    // Two packages paid ahead: the track is what is left.
    expect(balanceBar(course({ lessons_left: 12 }))).toMatchObject({ total: 12, fill: 1, mark: 2 / 12 });
    expect(balanceBar(course({ lessons_left: 0 }))).toMatchObject({ fill: 0, left: 0 });
    // No count of lessons (a plan without a lesson length): no bar.
    expect(balanceBar(course({ lessons_left: null }))).toBeNull();
    // No plan (the kids' look) and no mark at nothing.
    expect(balanceBar(course({ plan: null, lessons_left: 3 }))).toEqual({ left: 3, total: 3, fill: 1, mark: null, markAt: null });
    expect(balanceBar(course({ plan: { ...course().plan!, renews_at_lessons_left: 0 } }))?.mark).toBeNull();
  });

  it('gives a child a dot a lesson, and the rest as a number', () => {
    expect(kidsDots(4)).toEqual({ dots: 4, more: 0 });
    expect(kidsDots(13)).toEqual({ dots: 10, more: 3 });
    expect(kidsDots(null)).toEqual({ dots: 0, more: 0 });
  });

  it('says how soon an invoice is due, on the instant the API wrote', () => {
    expect(dueSoon(null, NOW)).toBeNull();
    expect(dueSoon({ utc: '2026-11-03T15:00:00Z' }, NOW)).toBe('soon');
    expect(dueSoon({ utc: '2026-11-10T15:00:00Z' }, NOW)).toBe('later');
    expect(dueSoon({ utc: '2026-11-01T15:00:00Z' }, NOW)).toBe('overdue');
  });

  it('opens the invoice to pay only for its payer', () => {
    expect(payHref(course({ waiting_invoice: waiting(null) }))).toBe('/family/invoices/inv-1');
    expect(payHref(course({ waiting_invoice: waiting(null, null) }))).toBeNull();
    expect(payHref(course())).toBeNull();
  });

  it('puts paying first, then what comes, then what to know', () => {
    const rows = balanceRows(
      course({
        waiting_invoice: waiting('2026-11-03T15:00:00Z'),
        expiry: { at: at('2026-11-05T00:00:00Z'), lessons: 2, soon: true, text: 'تنتهي صلاحية 2 من الحصص' },
        renewal_note: 'لا تجديد',
      }),
      NOW,
    );
    expect(rows.map((r) => r.kind)).toEqual(['waiting', 'expiry', 'note']);
    expect(rows[0]).toMatchObject({ tone: 'attention', due: 'soon', href: '/family/invoices/inv-1', amount: '940 ر.س.', dueLabel: 'label 2026-11-03T15:00:00Z' });
    expect(rows[1]).toMatchObject({ tone: 'attention' });

    const overdue = balanceRows(course({ waiting_invoice: waiting('2026-10-30T15:00:00Z') }), NOW);
    expect(overdue[0]).toMatchObject({ tone: 'danger', due: 'overdue' });

    const next = balanceRows(
      course({
        expiry: { at: at('2026-12-20T00:00:00Z'), lessons: 5, soon: false, text: 'تنتهي الصلاحية' },
        next_invoice: { at_lessons_left: 2, lessons_until: 3, amount: '940 ر.س.', amount_minor: 94000, currency: 'SAR', on: at('2026-11-12T00:00:00Z'), text: 'تصدر الفاتورة التالية عندما يبقى 2 حصص' },
      }),
      NOW,
    );
    expect(next.map((r) => [r.kind, r.tone])).toEqual([['expiry', 'muted'], ['next', 'info']]);
    expect(next[1]).toMatchObject({ now: false });
    // At the mark already: it goes out now.
    const due = { at_lessons_left: 2, lessons_until: 0, amount: '940 ر.س.', amount_minor: 94000, currency: 'SAR', on: null, text: 'تصدر قريبًا' };
    expect(balanceRows(course({ next_invoice: due }), NOW)[0]).toMatchObject({ kind: 'next', now: true });
    // A student's look: the API leaves money out, so nothing about it is said.
    expect(balanceRows(course({ viewer_stage: 'teen', is_payer: null }), NOW)).toEqual([]);
  });

  it('reads in the look of the person signed in', () => {
    expect(balanceLook({ viewer_stage: 'kids' })).toBe('kids');
    expect(balanceLook({ viewer_stage: 'middle' })).toBe('student');
    expect(balanceLook({ viewer_stage: 'teen' })).toBe('student');
    expect(balanceLook({ viewer_stage: 'classic' })).toBe('classic');
  });

  it('refreshes when its invoice comes due soon, then past due', () => {
    expect(balanceMoments(course({ waiting_invoice: waiting('2026-11-03T15:00:00Z') }))).toEqual(['2026-11-02T03:00:00.000Z', '2026-11-03T15:00:00.000Z']);
    expect(balanceMoments(course())).toEqual([]);
  });
});

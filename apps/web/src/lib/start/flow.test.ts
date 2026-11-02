import { describe, expect, it } from 'vitest';
import {
  choiceFrom,
  choose,
  chooseGroup,
  firstLessonOf,
  initialPicks,
  inWeekOrder,
  keepFree,
  learnerOf,
  nextStep,
  plansByFormat,
  previousStep,
  priceOf,
  queryOf,
  refusalOf,
  skippedOf,
  skipsOf,
  startedSkips,
  startProblemOf,
  stepToShow,
  stepsFor,
  teachersFor,
  timesReady,
  togglePick,
} from './flow';
import { payState } from './pay';
import type { StartPlan, StartSlots } from './types';

const L = '01a1997b-bd0c-71e3-80c0-a278490b0f3c';
const S = '01a1416a-b328-709a-93ec-d39831e03623';
const P = '01a1416a-d256-7043-b888-59fd579d902d';
const T = '01a1416a-b578-72b5-9e4d-9dfa529ed0fd';
const G = '01a19590-e69e-7199-89ef-ed1b01d49aac';

const time = (key: string, utc: string) => ({ key, start: key.split('|')[1]!, end: '', label: key, first: { utc, local: utc, timezone: 'Africa/Cairo', label: utc } });
const slots: StartSlots = {
  format: 'private',
  timezone: 'Africa/Cairo',
  per_week: 2,
  suggested: ['2|20:00', '6|20:00'],
  first_day: null,
  groups: [],
  days: [
    { weekday: 6, name: 'السبت', times: [time('6|19:30', '2026-11-07T17:30:00Z'), time('6|20:00', '2026-11-07T18:00:00Z')] },
    { weekday: 2, name: 'الثلاثاء', times: [time('2|17:00', '2026-11-10T15:00:00Z'), time('2|20:00', '2026-11-10T18:00:00Z')] },
  ],
};
const money = (minor: number) => ({ amount_minor: minor, currency: 'SAR', amount: `${minor / 100} ر.س.` });
const plan = (over: Partial<StartPlan> = {}): StartPlan => ({
  id: P, name: '8', format: 'private', shared: false, session_minutes: 30, sessions_per_week: 2, sessions_per_block: 8, validity_days: 42,
  renews_at_lessons_left: 2, price: money(40000), per_lesson: money(5000), first_invoice: { discounts: [], total: money(40000), offer_applied: false }, ...over,
});

describe('the choice in the address', () => {
  it('reads ids and weekly times, leaving out anything malformed', () => {
    const choice = choiceFrom({ learner: L, subject: S, plan: 'nope', teacher: T, times: '2|20:00,6|20:00,2|20:00,bad,9|10:00' });
    expect(choice).toEqual({ learner: L, subject: S, plan: undefined, teacher: T, group: undefined, times: ['2|20:00', '6|20:00'] });
    expect(choiceFrom({ learner: '<script>' }).learner).toBeUndefined();
  });

  it('writes it back with the step and what the flow carries (the recommended plan, the trial)', () => {
    const query = new URLSearchParams(queryOf({ learner: L, subject: S, plan: P, teacher: T, times: ['2|20:00', '6|20:00'] }, 'review', { rec: P, trial: undefined }));
    expect(query.get('times')).toBe('2|20:00,6|20:00');
    expect(query.get('step')).toBe('review');
    expect(query.get('rec')).toBe(P);
    expect(query.has('trial')).toBe(false);
    expect(choiceFrom(Object.fromEntries(query))).toMatchObject({ learner: L, plan: P, times: ['2|20:00', '6|20:00'] });
  });
});

describe('the steps', () => {
  it('asks whose course only with several learners, and no weekly times for a group', () => {
    expect(stepsFor(1, plan())).toEqual(['subject', 'plan', 'teacher', 'times', 'review']);
    expect(stepsFor(2, plan())).toEqual(['learner', 'subject', 'plan', 'teacher', 'times', 'review']);
    expect(stepsFor(1, plan({ format: 'group' }))).toEqual(['subject', 'plan', 'teacher', 'review']);
  });

  it('shows the step asked for while every one before it is done, else the first not done', () => {
    const fromReport = { learner: L, subject: S, plan: P, teacher: T, times: [] };
    expect(stepToShow(undefined, fromReport, 1, plan())).toBe('times');
    expect(stepToShow('review', fromReport, 1, plan())).toBe('times');
    expect(stepToShow('plan', fromReport, 1, plan())).toBe('plan');
    expect(stepToShow('review', { ...fromReport, times: ['2|20:00', '6|20:00'] }, 1, plan())).toBe('review');
    expect(stepToShow('nonsense', { learner: L, times: [] }, 2, undefined)).toBe('subject');
    expect(stepToShow(undefined, { times: [] }, 2, undefined)).toBe('learner');
    // A group plan: the group stands for the teacher, and no times.
    expect(stepToShow('review', { learner: L, subject: S, plan: G, group: G, times: [] }, 1, plan({ id: G, format: 'group' }))).toBe('review');
  });

  it('goes forward and back through the plan\'s steps', () => {
    expect(nextStep('teacher', 1, plan())).toBe('times');
    expect(nextStep('teacher', 1, plan({ format: 'group' }))).toBe('review');
    expect(previousStep('review', 1, plan({ format: 'group' }))).toBe('teacher');
    expect(previousStep('subject', 1, plan())).toBeNull();
    expect(previousStep('subject', 2, plan())).toBe('learner');
  });

  it('chooses again what comes after a changed choice', () => {
    const full = { learner: L, subject: S, plan: P, teacher: T, times: ['2|20:00'] };
    expect(choose(full, 'subject', S)).toBe(full);
    expect(choose(full, 'subject', P)).toEqual({ learner: L, subject: P, times: [] });
    const plans = [plan(), plan({ id: 'other', sessions_per_week: 1 }), plan({ id: G, format: 'group' })];
    // Another private plan keeps the teacher, never the times.
    expect(choose(full, 'plan', 'other', plans)).toEqual({ learner: L, subject: S, plan: 'other', teacher: T, times: [] });
    expect(choose(full, 'plan', G, plans).teacher).toBeUndefined();
    expect(chooseGroup(full, G)).toEqual({ learner: L, subject: S, plan: P, teacher: undefined, group: G, times: [] });
  });

  it('takes the only learner without asking', () => {
    const one = { learner: { id: L, name: 'ليان' }, learners: [{ id: L, name: 'ليان', is_self: false, consent_waiting: true }] };
    expect(learnerOf(one, { times: [] })).toBe(L);
    expect(learnerOf({ ...one, learners: [...one.learners, { id: 'x', name: 'آدم', is_self: false, consent_waiting: false }] }, { times: [] })).toBeUndefined();
  });
});

describe('the weekly times', () => {
  it('takes exactly the lessons a week, one a day', () => {
    expect(timesReady(['2|20:00', '6|20:00'], 2)).toBe(true);
    expect(timesReady(['2|20:00', '2|17:00'], 2)).toBe(false);
    expect(timesReady(['2|20:00'], 2)).toBe(false);
  });

  it('presses: another time on a chosen day takes its place; a new day waits while the week is full', () => {
    expect(togglePick([], '2|20:00', 2)).toEqual({ picks: ['2|20:00'] });
    expect(togglePick(['2|20:00'], '2|17:00', 2)).toEqual({ picks: ['2|17:00'] });
    expect(togglePick(['2|20:00', '6|20:00'], '6|19:30', 2)).toEqual({ picks: ['2|20:00', '6|19:30'] });
    expect(togglePick(['2|20:00', '6|20:00'], '1|10:00', 2)).toEqual({ picks: ['2|20:00', '6|20:00'], full: true });
    expect(togglePick(['2|20:00', '6|20:00'], '2|20:00', 2)).toEqual({ picks: ['6|20:00'] });
  });

  it('starts from the address while its times are free, else the suggested pattern', () => {
    expect(initialPicks(['6|19:30'], slots)).toEqual(['6|19:30']);
    expect(initialPicks(['5|10:00'], slots)).toEqual(['2|20:00', '6|20:00']);
    expect(initialPicks([], { ...slots, suggested: ['2|20:00'] })).toEqual([]);
    expect(initialPicks([], null)).toEqual([]);
  });

  it('keeps the picks still free after a time was taken meanwhile', () => {
    expect(keepFree(['2|20:00', '6|20:00'], ['6|20:00', '6|19:30'])).toEqual(['6|20:00']);
  });

  it('reads the first lesson and the week in order', () => {
    expect(firstLessonOf(['2|20:00', '6|20:00'], slots)?.key).toBe('6|20:00');
    expect(inWeekOrder(['2|20:00', '6|20:00'], slots).map((t) => t.key)).toEqual(['6|20:00', '2|20:00']);
    expect(firstLessonOf([], slots)).toBeNull();
  });
});

describe('plans, teachers and prices', () => {
  it('keeps private lessons and groups apart', () => {
    const kinds = plansByFormat([plan(), plan({ id: G, format: 'group' })]);
    expect(kinds.private.map((p) => p.id)).toEqual([P]);
    expect(kinds.group.map((p) => p.id)).toEqual([G]);
  });

  it('puts the trial\'s teacher first, and only teachers who take private lessons', () => {
    const list = teachersFor([
      { id: 'a', private: true, trial_teacher: false },
      { id: 'b', private: false, trial_teacher: false },
      { id: 'c', private: true, trial_teacher: true },
    ]);
    expect(list.map((t) => t.id)).toEqual(['c', 'a']);
  });

  it('strikes the plan\'s price through when the first invoice comes to less', () => {
    expect(priceOf(plan())).toEqual({ total: '400 ر.س.', was: null, offer: false, discounts: [] });
    const offer = plan({ first_invoice: { discounts: [{ label: 'TRIAL (10%)', amount_minor: -4000, amount: '-40' }], total: money(36000), offer_applied: true } });
    expect(priceOf(offer)).toMatchObject({ total: '360 ر.س.', was: '400 ر.س.', offer: true });
  });
});

describe('refusals', () => {
  const problem = (reason: string, more: Record<string, unknown> = {}) => startProblemOf({ status: 409, title: `words for ${reason}`, type: 'start-refused', extra: { detail: `words for ${reason}`, reason, ...more } });

  it('reads the API\'s problem with what each refusal carries', () => {
    expect(problem('time_taken', { times: [{ key: '6|20:00', weekday: 6 }, { nope: 1 }] }).times).toEqual([{ key: '6|20:00' }]);
    expect(problem('price_changed', { price: money(45000) }).price?.amount_minor).toBe(45000);
    expect(startProblemOf({ status: 409, title: 'consent', type: 'guardian-consent-needed', extra: { reason: 'consent', student_id: L } }).student_id).toBe(L);
  });

  it('answers each refusal with its own next step', () => {
    expect(refusalOf(problem('time_taken', { times: [{ key: '6|20:00' }] }), '')).toEqual({ kind: 'times', message: 'words for time_taken', step: 'times', free: ['6|20:00'] });
    expect(refusalOf(problem('price_changed', { price: money(45000) }), '')).toMatchObject({ kind: 'price', step: 'review' });
    expect(refusalOf(problem('course_pending_own', { enrollment_id: L }), '')).toMatchObject({ kind: 'course', enrollmentId: L, pendingOwn: true, pending: true });
    expect(refusalOf(problem('has_course', { enrollment_id: L }), '')).toMatchObject({ kind: 'course', pendingOwn: false, pending: false });
    expect(refusalOf(problem('course_paused'), '')).toMatchObject({ kind: 'course' });
    for (const reason of ['academy_full', 'self_serve_off', 'family_only']) expect(refusalOf(problem(reason), '').kind).toBe('contact');
    expect(refusalOf(problem('no_price'), '')).toMatchObject({ kind: 'choose', step: 'plan' });
    expect(refusalOf(problem('teacher_unavailable'), '')).toMatchObject({ kind: 'choose', step: 'teacher' });
    expect(refusalOf(problem('group_full'), '')).toMatchObject({ kind: 'choose', step: 'teacher' });
    expect(refusalOf(problem('times_count'), '')).toMatchObject({ kind: 'choose', step: 'times' });
    expect(refusalOf(problem('something_new'), '')).toEqual({ kind: 'say', message: 'words for something_new' });
    expect(refusalOf({ status: 409, type: 'guardian-consent-needed', detail: 'agree', reason: 'consent', student_id: L }, '')).toEqual({ kind: 'consent', message: 'agree', studentId: L });
    expect(refusalOf({ status: 422, detail: '' }, 'fallback')).toEqual({ kind: 'say', message: 'fallback' });
  });
});

describe('weeks a time starts without', () => {
  const at = (utc: string) => ({ utc, local: utc, timezone: 'Africa/Cairo', label: utc });
  const holiday = { date: '2026-11-17', reason: 'holiday', at: at('2026-11-17T15:00:00Z'), text: 'لا حصة يوم الثلاثاء 17 نوفمبر: إجازة الأكاديمية' };
  const leave = { date: '2026-11-14', reason: 'teacher_leave', at: at('2026-11-14T18:00:00Z'), text: 'لا حصة يوم السبت 14 نوفمبر: إجازة المعلّم' };
  const withSkips: StartSlots = {
    ...slots,
    days: [
      { weekday: 6, name: 'السبت', times: [time('6|19:30', '2026-11-07T17:30:00Z'), { ...time('6|20:00', '2026-11-07T18:00:00Z'), skips: [leave] }] },
      { weekday: 2, name: 'الثلاثاء', times: [{ ...time('2|17:00', '2026-11-10T15:00:00Z'), skips: [holiday] }, { ...time('2|20:00', '2026-11-10T18:00:00Z'), skips: [] }] },
    ],
  };

  it('reads a time\'s skips from the JSON as it comes, leaving out what is malformed', () => {
    expect(skipsOf(withSkips.days[1]!.times[0])).toEqual([holiday]);
    expect(skipsOf(withSkips.days[0]!.times[0])).toEqual([]);
    expect(skipsOf({ skips: 'nope' })).toEqual([]);
    expect(skipsOf({ skips: [null, 3, { date: '2026-11-17' }, { text: 'no day' }, { date: '2026-11-17', text: '  ' }] })).toEqual([]);
    expect(skipsOf({ skips: [{ date: '2026-11-17', text: ' كلمات ', at: 'bad' }] })).toEqual([{ date: '2026-11-17', reason: '', at: null, text: 'كلمات' }]);
  });

  it('lists the chosen times\' skipped weeks in date order, each once', () => {
    expect(skippedOf(['2|17:00', '6|20:00'], withSkips)).toEqual([leave, holiday]);
    expect(skippedOf(['2|20:00', '6|19:30'], withSkips)).toEqual([]);
    expect(skippedOf(['2|17:00'], null)).toEqual([]);
  });

  it('tells the welcome the skipped weeks apart from the days the teacher or the learner was busy', () => {
    expect(startedSkips({ skipped: [holiday, leave], skipped_dates: ['2026-11-14', '2026-11-17', '2026-11-21'] })).toEqual({ weeks: [leave, holiday], busy: 1 });
    expect(startedSkips({ skipped: [holiday], skipped_dates: ['2026-11-17'] })).toEqual({ weeks: [holiday], busy: 0 });
    // A start saved by an older page has no `skipped`: every day counts as busy, as before.
    expect(startedSkips({ skipped_dates: ['2026-11-21'] })).toEqual({ weeks: [], busy: 1 });
  });
});

describe('back from the gateway', () => {
  const owed = { status: 'issued', outstanding: { amount_minor: 36000, amount: '360' } };
  it('reads paid, confirming, failed, refund and waiting', () => {
    expect(payState({ status: 'paid', outstanding: { amount_minor: 0, amount: '0' } }, { status: 'paid' })).toBe('paid');
    expect(payState({ status: 'paid', outstanding: { amount_minor: 0, amount: '0' } }, null)).toBe('paid');
    expect(payState(owed, { status: 'open' })).toBe('confirming');
    expect(payState(owed, { status: 'failed' })).toBe('failed');
    expect(payState(owed, { status: 'expired' })).toBe('failed');
    expect(payState(owed, { status: 'refund_due' })).toBe('refund');
    expect(payState(owed, null)).toBe('waiting');
  });
});

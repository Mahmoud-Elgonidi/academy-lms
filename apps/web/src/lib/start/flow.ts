import type { SkippedWeek, StartOptions, StartPlan, StartSlots, StartedCourse, WeeklyTime } from './types';

/**
 * The start flow's own reckoning (docs/architecture/02, section 27, J3),
 * pure so it is tested apart: what the family chose (kept in the address, so
 * going back and refreshing keep it), which steps the plan asks for, which one
 * to show, picking weekly times (exactly a plan's lessons a week, one a day),
 * and what each refusal of the API asks the page to do next.
 */

export type Step = 'learner' | 'subject' | 'plan' | 'teacher' | 'times' | 'review';

export const STEPS: readonly Step[] = ['learner', 'subject', 'plan', 'teacher', 'times', 'review'];

/** What the family chose so far. */
export type Choice = {
  learner?: string;
  subject?: string;
  plan?: string;
  teacher?: string;
  group?: string;
  /** The weekly times' keys ("2|20:00": the weekday on the family's clock and its start). */
  times: string[];
};

type Params = Record<string, string | string[] | undefined>;

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const KEY = /^[1-7]\|\d{2}:\d{2}$/;

const one = (value: string | string[] | undefined): string | undefined => (Array.isArray(value) ? value[0] : value);
const id = (value: string | string[] | undefined): string | undefined => {
  const found = one(value);
  return found && UUID.test(found) ? found : undefined;
};

/** The choice from the page's address; anything malformed is left out. */
export function choiceFrom(params: Params): Choice {
  const learner = one(params.learner);
  const times = (one(params.times) ?? '').split(',').filter((key) => KEY.test(key));
  return {
    learner: learner && /^[\w-]{1,64}$/.test(learner) ? learner : undefined,
    subject: id(params.subject),
    plan: id(params.plan),
    teacher: id(params.teacher),
    group: id(params.group),
    times: [...new Set(times)].slice(0, 7),
  };
}

/** The address of a choice and a step (the start flow's own query, nothing else). */
export function queryOf(choice: Choice, step?: Step, extra: Record<string, string | undefined> = {}): string {
  const query = new URLSearchParams();
  const put = (name: string, value: string | undefined) => {
    if (value) query.set(name, value);
  };
  put('learner', choice.learner);
  put('subject', choice.subject);
  put('plan', choice.plan);
  put('teacher', choice.teacher);
  put('group', choice.group);
  if (choice.times.length) query.set('times', choice.times.join(','));
  put('step', step);
  for (const [name, value] of Object.entries(extra)) put(name, value);
  return query.toString();
}

/** The steps a choice goes through: whose course (only with several learners), the subject, the plan, the teacher or group, the weekly times (a private plan), the review. */
export function stepsFor(learners: number, plan: Pick<StartPlan, 'format'> | undefined): Step[] {
  return STEPS.filter((step) => (step === 'learner' ? learners > 1 : step === 'times' ? plan?.format !== 'group' : true));
}

/** Whether a step's own choice is made. */
export function isDone(step: Step, choice: Choice, plan: Pick<StartPlan, 'format' | 'sessions_per_week'> | undefined): boolean {
  switch (step) {
    case 'learner':
      return Boolean(choice.learner);
    case 'subject':
      return Boolean(choice.subject);
    case 'plan':
      return Boolean(plan);
    case 'teacher':
      return plan?.format === 'group' ? Boolean(choice.group) : Boolean(choice.teacher);
    case 'times':
      return plan ? timesReady(choice.times, plan.sessions_per_week) : false;
    case 'review':
      return false;
  }
}

/**
 * The step to show: the one asked for while every step before it is done,
 * else the first not done (a refreshed or shared address lands where the
 * family can go on).
 */
export function stepToShow(wanted: string | undefined, choice: Choice, learners: number, plan: Pick<StartPlan, 'format' | 'sessions_per_week'> | undefined): Step {
  const steps = stepsFor(learners, plan);
  const firstOpen = steps.find((step) => !isDone(step, choice, plan)) ?? 'review';
  const asked = steps.find((step) => step === wanted);
  if (!asked) return firstOpen;
  return steps.indexOf(asked) <= steps.indexOf(firstOpen) ? asked : firstOpen;
}

/** The step after this one, and before it. */
export function nextStep(step: Step, learners: number, plan: Pick<StartPlan, 'format'> | undefined): Step | null {
  const steps = stepsFor(learners, plan);
  return steps[steps.indexOf(step) + 1] ?? null;
}
export function previousStep(step: Step, learners: number, plan: Pick<StartPlan, 'format'> | undefined): Step | null {
  const steps = stepsFor(learners, plan);
  const at = steps.indexOf(step);
  return at > 0 ? steps[at - 1]! : null;
}

/** What changes when an earlier choice changes: everything after it is chosen again (a new plan keeps the teacher while it still has one). */
export function choose(choice: Choice, step: Step, value: string, plans: Pick<StartPlan, 'id' | 'format' | 'sessions_per_week'>[] = []): Choice {
  switch (step) {
    case 'learner':
      return value === choice.learner ? choice : { learner: value, times: [] };
    case 'subject':
      return value === choice.subject ? choice : { learner: choice.learner, subject: value, times: [] };
    case 'plan': {
      if (value === choice.plan) return choice;
      const before = plans.find((p) => p.id === choice.plan);
      const after = plans.find((p) => p.id === value);
      // The same teacher still fits a private plan of another shape; the times are chosen again for it.
      const keepTeacher = before?.format === 'private' && after?.format === 'private';
      return { learner: choice.learner, subject: choice.subject, plan: value, teacher: keepTeacher ? choice.teacher : undefined, times: [] };
    }
    case 'teacher':
      return value === choice.teacher ? choice : { ...choice, teacher: value, group: undefined, times: [] };
    case 'times':
    case 'review':
      return choice;
  }
}

/** A group plan's group. */
export function chooseGroup(choice: Choice, group: string): Choice {
  return { ...choice, group, teacher: undefined, times: [] };
}

/** The weekday of a time's key (1 Monday … 7 Sunday). */
export function weekdayOf(key: string): number {
  return Number(key.split('|')[0]);
}

/** Exactly the plan's lessons a week, each on a day of its own. */
export function timesReady(picks: readonly string[], perWeek: number): boolean {
  return picks.length === perWeek && new Set(picks.map(weekdayOf)).size === picks.length;
}

/**
 * A time pressed: chosen, or no longer chosen. Another time on a day already
 * chosen takes that day's place (one lesson a day); with every lesson of the
 * week chosen, a new day waits until one is taken off (`full`).
 */
export function togglePick(picks: readonly string[], key: string, perWeek: number): { picks: string[]; full?: true } {
  if (picks.includes(key)) return { picks: picks.filter((k) => k !== key) };
  const day = weekdayOf(key);
  const sameDay = picks.find((k) => weekdayOf(k) === day);
  if (sameDay) return { picks: picks.map((k) => (k === sameDay ? key : k)) };
  if (picks.length >= perWeek) return { picks: [...picks], full: true };
  return { picks: [...picks, key] };
}

/** Every weekly time of the slots by key. */
export function timesByKey(slots: Pick<StartSlots, 'days'> | null | undefined): Map<string, WeeklyTime> {
  const found = new Map<string, WeeklyTime>();
  for (const day of slots?.days ?? []) for (const time of day.times) found.set(time.key, time);
  return found;
}

/**
 * The times to start the picker with: those in the address that are still
 * free, else the API's suggested pattern (when it fits the plan), else none.
 */
export function initialPicks(fromAddress: readonly string[], slots: Pick<StartSlots, 'days' | 'suggested' | 'per_week'> | null | undefined): string[] {
  const free = timesByKey(slots);
  const kept = fromAddress.filter((key) => free.has(key));
  if (kept.length > 0) return kept;
  const suggested = (slots?.suggested ?? []).filter((key) => free.has(key));
  return slots && timesReady(suggested, slots.per_week) ? suggested : [];
}

/** After a time was taken meanwhile: the picks still free now (the API's fresh times), in the order chosen. */
export function keepFree(picks: readonly string[], freshKeys: readonly string[]): string[] {
  const fresh = new Set(freshKeys);
  return picks.filter((key) => fresh.has(key));
}

/** The soonest first lesson of the chosen times (the course's first lesson). */
export function firstLessonOf(picks: readonly string[], slots: Pick<StartSlots, 'days'> | null | undefined): WeeklyTime | null {
  const free = timesByKey(slots);
  return picks
    .map((key) => free.get(key))
    .filter((t): t is WeeklyTime => Boolean(t))
    .sort((a, b) => Date.parse(a.first.utc) - Date.parse(b.first.utc))[0] ?? null;
}

/** The chosen times in the week's order (the days as the API listed them). */
export function inWeekOrder(picks: readonly string[], slots: Pick<StartSlots, 'days'> | null | undefined): WeeklyTime[] {
  const order: WeeklyTime[] = [];
  for (const day of slots?.days ?? []) for (const time of day.times) if (picks.includes(time.key)) order.push(time);
  return order;
}

/**
 * The weeks a weekly time starts without, as the API wrote them (`skips`,
 * untyped in the OpenAPI document): only entries with a day and a sentence,
 * in date order; anything else is left out.
 */
export function skipsOf(time: Pick<WeeklyTime, 'skips'> | null | undefined): SkippedWeek[] {
  return cleanSkips(time?.skips);
}

function cleanSkips(raw: unknown): SkippedWeek[] {
  if (!Array.isArray(raw)) return [];
  const found: SkippedWeek[] = [];
  for (const item of raw as Record<string, unknown>[]) {
    if (!item || typeof item !== 'object') continue;
    const { date, reason, at, text } = item;
    if (typeof date !== 'string' || typeof text !== 'string' || text.trim() === '') continue;
    const when = at && typeof at === 'object' && typeof (at as { utc?: unknown }).utc === 'string' ? (at as SkippedWeek['at']) : null;
    found.push({ date, reason: typeof reason === 'string' ? reason : '', at: when, text: text.trim() });
  }
  return byDate(found);
}

/** In date order, each sentence once (two times on one day share a holiday's). */
function byDate(weeks: SkippedWeek[]): SkippedWeek[] {
  const seen = new Set<string>();
  return [...weeks]
    .sort((a, b) => (a.at && b.at ? Date.parse(a.at.utc) - Date.parse(b.at.utc) : a.date.localeCompare(b.date)))
    .filter((w) => (seen.has(w.text) ? false : (seen.add(w.text), true)));
}

/** The weeks the chosen times start without, in date order (the review and the summary list them). */
export function skippedOf(picks: readonly string[], slots: Pick<StartSlots, 'days'> | null | undefined): SkippedWeek[] {
  const free = timesByKey(slots);
  return byDate(picks.flatMap((key) => skipsOf(free.get(key))));
}

/**
 * What the welcome says of the days a course started without: the skipped
 * weeks in the API's words, and how many other days were left out because the
 * teacher or the learner was already booked (`skipped_dates` counts both).
 */
export function startedSkips(course: Pick<StartedCourse, 'skipped' | 'skipped_dates'>): { weeks: SkippedWeek[]; busy: number } {
  const weeks = cleanSkips(course.skipped);
  const dates = Array.isArray(course.skipped_dates) ? course.skipped_dates.length : 0;
  return { weeks, busy: Math.max(0, dates - weeks.length) };
}

/** Plans the way the family reads them: private lessons first, then groups. */
export function plansByFormat<P extends Pick<StartPlan, 'format'>>(plans: readonly P[]): { private: P[]; group: P[] } {
  return { private: plans.filter((p) => p.format !== 'group'), group: plans.filter((p) => p.format === 'group') };
}

/** The trial's teacher first (they know the learner), then the rest as the API ranked them; only those who take this kind of plan. */
export function teachersFor<T extends { private: boolean; trial_teacher: boolean }>(teachers: readonly T[]): T[] {
  return teachers.filter((t) => t.private).sort((a, b) => Number(b.trial_teacher) - Number(a.trial_teacher));
}

/** What the first invoice shows before confirming: the plan's price, what comes off, and the total (the offer's price struck through when it applies). */
export function priceOf(plan: Pick<StartPlan, 'price' | 'first_invoice'>): { total: string; was: string | null; offer: boolean; discounts: StartPlan['first_invoice']['discounts'] } {
  const discounted = plan.first_invoice.total && plan.first_invoice.discounts.length > 0 && plan.first_invoice.total.amount_minor < plan.price.amount_minor;
  return {
    total: discounted ? plan.first_invoice.total!.amount : plan.price.amount,
    was: discounted ? plan.price.amount : null,
    offer: plan.first_invoice.offer_applied,
    discounts: plan.first_invoice.discounts,
  };
}

/** A refusal of the API (start-refused or guardian-consent-needed), as the page reads it. */
export type StartProblem = {
  status: number;
  type?: string;
  /** The sentence to show: what happened and what to do next. */
  detail: string;
  reason?: string;
  /** time_taken: the times free now. */
  times?: { key: string }[];
  /** price_changed: the price today. */
  price?: { amount_minor: number; currency: string; amount: string };
  /** A course in the subject (has_course, course_pending_own, …). */
  enrollment_id?: string;
  /** guardian-consent-needed: the child to agree for. */
  student_id?: string;
};

/**
 * What a refusal asks the page to do: agree first and send again
 * (consent), choose times again from the fresh ones (times), confirm at the
 * new price (price), open the course in the subject (course), reach the
 * academy (contact), or choose the plan, teacher or group again; anything
 * else is said where the family pressed.
 */
export type Refusal =
  | { kind: 'consent'; message: string; studentId: string }
  | { kind: 'times'; message: string; step: 'times'; free: string[] | null }
  | { kind: 'price'; message: string; step: 'review'; price: StartProblem['price'] | null }
  | { kind: 'course'; message: string; step: 'subject'; enrollmentId: string | null; pendingOwn: boolean; pending: boolean }
  | { kind: 'contact'; message: string }
  | { kind: 'choose'; message: string; step: 'plan' | 'teacher' | 'times' | 'subject' }
  | { kind: 'say'; message: string };

const COURSE = ['has_course', 'course_pending_own', 'course_pending', 'course_paused', 'course_ending'];
const CONTACT = ['academy_full', 'self_serve_off', 'family_only'];

export function refusalOf(problem: StartProblem, fallback: string): Refusal {
  const message = problem.detail || fallback;
  if (problem.type === 'guardian-consent-needed' || problem.reason === 'consent') {
    return problem.student_id ? { kind: 'consent', message, studentId: problem.student_id } : { kind: 'say', message };
  }
  const reason = problem.reason ?? '';
  if (reason === 'time_taken') return { kind: 'times', message, step: 'times', free: problem.times ? problem.times.map((t) => t.key) : null };
  if (reason === 'price_changed') return { kind: 'price', message, step: 'review', price: problem.price ?? null };
  if (COURSE.includes(reason)) {
    return { kind: 'course', message, step: 'subject', enrollmentId: problem.enrollment_id ?? null, pendingOwn: reason === 'course_pending_own', pending: reason === 'course_pending' || reason === 'course_pending_own' };
  }
  if (CONTACT.includes(reason)) return { kind: 'contact', message };
  if (reason === 'no_plan' || reason === 'no_price') return { kind: 'choose', message, step: 'plan' };
  if (['teacher_unavailable', 'group_full', 'group_unavailable', 'group_needed'].includes(reason)) return { kind: 'choose', message, step: 'teacher' };
  if (reason === 'times_count' || reason === 'times_needed') return { kind: 'choose', message, step: 'times' };
  return { kind: 'say', message };
}

/** The problem in an API's answer, as the start flow reads it (members of the problem beside its words). */
export function startProblemOf(problem: { status: number; title: string; type?: string; extra?: Record<string, unknown> }): StartProblem {
  const extra = problem.extra ?? {};
  const str = (name: string) => (typeof extra[name] === 'string' ? (extra[name] as string) : undefined);
  const times = Array.isArray(extra.times) ? (extra.times as { key?: unknown }[]).filter((t) => typeof t?.key === 'string').map((t) => ({ key: t.key as string })) : undefined;
  const price = extra.price && typeof extra.price === 'object' ? (extra.price as StartProblem['price']) : undefined;
  return {
    status: problem.status,
    type: problem.type,
    detail: str('detail') ?? problem.title,
    reason: str('reason'),
    times,
    price,
    enrollment_id: str('enrollment_id'),
    student_id: str('student_id'),
  };
}

/** The learner the flow is about: the one chosen, else the only one. */
export function learnerOf(options: Pick<StartOptions, 'learners' | 'learner'>, choice: Choice): string | undefined {
  if (choice.learner && options.learners.some((l) => l.id === choice.learner)) return choice.learner;
  return options.learners.length === 1 ? options.learners[0]!.id : undefined;
}

/** A start saved for the welcome after it (this tab only): by course, and by invoice (a coupon issues the invoice again). */
export const STARTED_KEY = (enrollment: string) => `lms_started:${enrollment}`;
export const INVOICE_COURSE_KEY = (invoice: string) => `lms_started_invoice:${invoice}`;

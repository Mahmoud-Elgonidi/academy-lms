import type { LocalTime } from '@/lib/time';

/**
 * The family's report after a free trial (docs/architecture/02, section 27,
 * J2), as the API writes it (GET site/trials/report/{token}, GET
 * family/trials/{session}), and what the portal works out from it: the
 * offer's state at this moment (the API's word, until the end it gave passes
 * on this device's clock), the time left on it as a countdown, the teacher's
 * words as lists, and the price of one lesson. Pure: no zones are converted
 * (the end is the API's UTC instant, the labels are the API's).
 */

export type Money = { price: string; amount_minor: number; currency: string };

export type ReportPlan = {
  id: string;
  name: string;
  format: 'private' | 'group';
  session_minutes: number;
  sessions_per_week: number;
  sessions_per_block: number;
  validity_days: number;
};

export type OfferState = 'open' | 'ended' | 'used' | 'started' | 'none';

export type ReportOffer = {
  percent: number;
  ends_at: LocalTime | null;
  price: Money | null;
  saving: Money | null;
  family_discount_bigger: boolean;
};

export type NextTrial = { kind: 'second' | 'rebook'; available: boolean; why_not: string | null; until?: LocalTime | null };

export type TrialReport = {
  id: string;
  learner: string;
  subject: { id: string; name: string };
  held_at: LocalTime | null;
  second: boolean;
  teacher: { name: string; card: { name: string; bio: string | null; photo_url: string | null } | null };
  level: string;
  strengths: string | null;
  to_work_on: string | null;
  note: string | null;
  recommendation: {
    plan: ReportPlan | null;
    plan_withdrawn: boolean;
    per_week: number | null;
    price: Money | null;
    region: { id: string; name: string; currency: string } | null;
  };
  course: { status: string } | null;
  offer_state: OfferState;
  offer: ReportOffer | null;
  next_trial: NextTrial | null;
};

/** A trial that did not take place, from its "book again" link (GET site/trials/rebook/{token}). */
export type RebookInfo = {
  learner: string;
  subject: { id: string; name: string };
  was_at: LocalTime | null;
  outcome: 'student_absent' | 'teacher_absent' | 'academy' | string;
  available: boolean;
  why_not: string | null;
  timezone: string;
};

/** A learner's free trial on their list (GET family/trials). */
export type FamilyTrial = {
  id: string;
  subject: { id: string; name: string };
  starts_at: LocalTime | null;
  teacher: string;
  kind: 'first' | 'rebook' | 'second' | string;
  status: 'booked' | 'attended' | 'missed' | 'cancelled' | string;
  outcome: string | null;
  report_ready: boolean;
  next: NextTrial | null;
};

/**
 * The offer as it stands now: the API's state, except that an open offer whose
 * end has passed on this clock has ended (the page was open when it ended).
 */
export function offerStateAt(state: OfferState, offer: ReportOffer | null, now: number): OfferState {
  if (state !== 'open') return state;
  if (!offer) return 'none';
  const ends = offer.ends_at ? Date.parse(offer.ends_at.utc) : Number.NaN;
  return Number.isFinite(ends) && ends <= now ? 'ended' : 'open';
}

export type TimeLeft = { ms: number; days: number; hours: number; minutes: number; seconds: number; over: boolean };

/** What is left until an instant (never below nothing), in whole days, hours, minutes and seconds. */
export function timeLeft(endsUtc: string, now: number): TimeLeft {
  const ends = Date.parse(endsUtc);
  const ms = Number.isFinite(ends) ? Math.max(0, ends - now) : 0;
  const total = Math.floor(ms / 1000);
  return {
    ms,
    days: Math.floor(total / 86_400),
    hours: Math.floor((total % 86_400) / 3600),
    minutes: Math.floor((total % 3600) / 60),
    seconds: total % 60,
    over: ms <= 0,
  };
}

export type Unit = 'days' | 'hours' | 'minutes' | 'seconds';

/**
 * The three numbers a countdown shows: days, hours and minutes while a day or
 * more is left; hours, minutes and seconds in the last day.
 */
export function countdownUnits(left: TimeLeft): { unit: Unit; value: number }[] {
  if (left.days > 0) return [{ unit: 'days', value: left.days }, { unit: 'hours', value: left.hours }, { unit: 'minutes', value: left.minutes }];
  return [{ unit: 'hours', value: left.hours }, { unit: 'minutes', value: left.minutes }, { unit: 'seconds', value: left.seconds }];
}

/** How near the end is: calm (more than a day), soon (the last day), last (the last hour). */
export function urgency(left: TimeLeft): 'calm' | 'soon' | 'last' {
  if (left.ms > 86_400_000) return 'calm';
  if (left.ms > 3_600_000) return 'soon';
  return 'last';
}

/** When the countdown next changes what it shows: each second in the last day, else at the next minute. */
export function nextTick(left: TimeLeft): number {
  if (left.over) return 0;
  if (left.days === 0) return (left.ms % 1000) || 1000;
  return (left.ms % 60_000) || 60_000;
}

/** The teacher's words as items: one a line ("-", "•" or a number in front left out); none when empty. */
export function itemsOf(text: string | null | undefined): string[] {
  return (text ?? '')
    .split(/\r?\n/)
    .map((line) => line.replace(/^\s*(?:[-–•*·]|\d+[.)-])\s*/u, '').trim())
    .filter(Boolean);
}

/** One lesson's share of a block's price, in minor units (rounded to a whole unit). */
export function perLessonMinor(price: Pick<Money, 'amount_minor'>, lessons: number): number | null {
  if (!Number.isInteger(lessons) || lessons <= 0) return null;
  return Math.round(price.amount_minor / lessons);
}

/** Minor units as money in the reader's language, with the currency's own decimals only when there are any. */
export function formatMinor(minor: number, currency: string, locale: string): string {
  const tag = locale === 'ar' ? 'ar-EG-u-nu-latn' : 'en-GB';
  let digits = 2;
  try {
    digits = new Intl.NumberFormat('en', { style: 'currency', currency }).resolvedOptions().maximumFractionDigits ?? 2;
  } catch {
    return `${(minor / 100).toFixed(2)} ${currency}`;
  }
  const amount = minor / 10 ** digits;
  const whole = Number.isInteger(amount);
  return new Intl.NumberFormat(tag, { style: 'currency', currency, minimumFractionDigits: whole ? 0 : digits, maximumFractionDigits: digits }).format(amount);
}

/** Whether the report has a plan to start with now (recommended, still offered). */
export function hasPlan(report: Pick<TrialReport, 'recommendation'>): boolean {
  return report.recommendation.plan !== null && !report.recommendation.plan_withdrawn;
}

/** A trial on the family's list, in one word for its badge: the report to read, to come, missed, cancelled, or waiting for its report. */
export function trialBadge(trial: Pick<FamilyTrial, 'status' | 'outcome' | 'report_ready'>): 'report' | 'booked' | 'waiting' | 'missed' | 'teacherMissed' | 'cancelled' | 'academyCancelled' {
  if (trial.report_ready) return 'report';
  if (trial.status === 'booked') return 'booked';
  if (trial.status === 'attended') return 'waiting';
  if (trial.status === 'missed') return trial.outcome === 'teacher_absent' ? 'teacherMissed' : 'missed';
  return trial.outcome === 'academy' ? 'academyCancelled' : 'cancelled';
}

/** This moment, for a page's first countdown numbers (written once on the server, then ticking in the browser). */
export function momentNow(): number {
  return Date.now();
}

import type { SaveCardOffer } from '@/lib/family/cards';
import type { LocalTime } from '../time';

/**
 * What the start flow reads and writes (docs/architecture/02, section 27,
 * J3): GET family/start/options and /slots, POST family/courses, and the
 * payer's invoice (GET family/invoices/{id}), as the API answers them.
 */

/** A sum as the API writes it: minor units, currency, and the words to show. */
export type Money = { amount_minor: number; currency: string; amount: string };

export type StartLearner = {
  id: string;
  name: string;
  /** The viewer themselves (an adult studying for themselves). */
  is_self: boolean;
  /** A child whose account waits for a guardian's consent (asked for before the course starts). */
  consent_waiting: boolean;
};

export type StartSubject = {
  id: string;
  name: string;
  /** The learner's course in it that has not ended. */
  course: { enrollment_id: string; status: 'pending_payment' | 'active' | 'paused' | string } | null;
  can_start: boolean;
  /** Why not, in the family's words (what to do instead). */
  why_not: string | null;
};

export type StartPlan = {
  id: string;
  name: string;
  format: 'private' | 'group';
  /** One balance for all the learner's courses on it. */
  shared: boolean;
  session_minutes: number;
  sessions_per_week: number;
  sessions_per_block: number;
  validity_days: number;
  renews_at_lessons_left: number;
  price: Money;
  per_lesson: Money;
  /** The first invoice for this learner before VAT: the sibling discount or the trial's offer (the bigger). */
  first_invoice: {
    discounts: { label: string; amount_minor: number; amount: string }[];
    total: Money | null;
    offer_applied: boolean;
  };
};

export type StartTeacher = {
  id: string;
  name: string;
  bio: string | null;
  photo_url: string | null;
  gender: 'female' | 'male' | null;
  /** The teacher of the learner's free trial in this subject. */
  trial_teacher: boolean;
  /** Takes new students for private lessons. */
  private: boolean;
  /** Open groups of theirs with a seat for the learner. */
  groups: number;
};

export type StartOffer = { state: 'open' | 'ended'; percent: number; ends_at: LocalTime | null };

export type StartOptions = {
  self_serve: boolean;
  why_off: string | null;
  learner: { id: string; name: string };
  learners: StartLearner[];
  subjects: StartSubject[];
  region: { id: string; name: string; currency: string } | null;
  plans: StartPlan[];
  teachers: StartTeacher[];
  offer: StartOffer | null;
  trial: { session_id: string; teacher_available: boolean } | null;
  payment: { unpaid_cancel_hours: number; notice_hours: number } | null;
};

/**
 * A week among the first four with no lesson at a weekly time (an academy
 * holiday or the teacher's leave): never booked. `date` is the day on the
 * family's clock, `text` the API's own sentence to show as written.
 */
export type SkippedWeek = { date: string; reason: 'holiday' | 'teacher_leave' | string; at: LocalTime | null; text: string };

/**
 * A teacher's weekly time on the family's clock, free in the first four weeks
 * but at most one skipped (`skips`); `first` is its first real lesson.
 * `skips` is read from the JSON as it comes (the OpenAPI document leaves it
 * untyped): through `skipsOf`, never directly.
 */
export type WeeklyTime = { key: string; start: string; end: string; label: string; first: LocalTime; skips?: unknown };

export type StartGroup = {
  id: string;
  name: string;
  teacher: { id: string; name: string };
  status: 'forming' | 'running' | string;
  seats: { capacity: number; taken: number; free: number };
  min_to_start: number | null;
  times: { weekday: number; start: string; label: string }[];
  next: LocalTime | null;
  held: number;
};

export type StartSlots = {
  format: 'private' | 'group';
  timezone: string;
  per_week: number;
  days: { weekday: number; name: string; times: WeeklyTime[] }[];
  /** A pattern of per_week times worth suggesting (keys), or null. */
  suggested: string[] | null;
  first_day: LocalTime | null;
  groups: StartGroup[];
};

/** An invoice as its payer reads it before paying (Billing's InvoiceSummary). */
export type InvoiceSummary = {
  id: string;
  number: string;
  status: 'issued' | 'partly_paid' | 'overdue' | 'paid' | 'void' | string;
  currency: string;
  lines: { kind: 'block' | 'extra' | 'discount' | string; description: string; quantity: number; amount_minor: number; amount: string }[];
  subtotal: { amount_minor: number; amount: string };
  vat: { rate: number; included: boolean; amount_minor: number; amount: string };
  total: { amount_minor: number; amount: string };
  paid: { amount_minor: number; amount: string };
  outstanding: { amount_minor: number; amount: string };
  due_at: LocalTime | null;
  /** personal: the trial's offer, which stays on. */
  coupon: { code: string; personal: boolean } | null;
  coupon_entry: { open: boolean; why_not: string | null };
  pay_url: string;
  document_url: string;
};

/** card_saved (J5b): the payer asked to keep the card and the gateway kept it; renewals are charged to it. */
export type CheckoutState = { id: string; status: 'paid' | 'open' | 'failed' | 'expired' | 'refund_due' | string; text: string; hint: string; card_saved?: boolean | string | null };

/** GET family/invoices/{id}: the invoice, and the latest online payment begun on it. */
export type InvoiceRead = { data: InvoiceSummary; checkout: CheckoutState | null; save_card?: SaveCardOffer };

/** POST family/courses: the course just started. */
export type StartedCourse = {
  enrollment_id: string;
  status: string;
  learner: { id: string; name: string };
  subject: { id: string; name: string };
  teacher: { id: string; name: string };
  plan: { id: string; name: string; format: 'private' | 'group'; session_minutes: number; sessions_per_week: number; sessions_per_block: number } | null;
  weekly_times: { weekday: number; start: string; label: string }[];
  first_lesson: LocalTime | null;
  lessons: number;
  /** Days with no lesson (the teacher's clock): the skipped weeks below, and any the teacher or the learner was already booked on. */
  skipped_dates: string[];
  /** The first weeks the course starts without (a holiday, the teacher's leave); missing in a start saved by an older page. */
  skipped?: SkippedWeek[];
  joined_package: boolean;
  group: { id: string; status: string; seat: number | null; capacity: number | null; started: boolean; min_to_start: number | null } | null;
  invoice: InvoiceSummary | null;
  pay_url: string | null;
  offer: { state: 'applied' | 'ended' | 'family_discount_bigger' | 'not_for_plan' | 'package' | 'none' | string; percent: number | null; message: string | null };
  payment: { pay_by: LocalTime | null; unpaid_cancel_hours: number; text: string; renews: string | null };
  can_cancel: boolean;
};

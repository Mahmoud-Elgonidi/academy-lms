import type { LocalTime } from '../time';
import type { Homework, Lesson as TeacherLesson, Roadmap } from '../teach/types';

/**
 * What the family endpoints answer (students and guardians), as the pages read
 * them. Every endpoint takes the learner (`learner_id`, through FamilyView):
 * a guardian's child, or the student themself.
 */

/** The look of the person signed in: a student's own stage, or classic for adults and every guardian. */
export type Stage = 'kids' | 'middle' | 'teen' | 'classic';
export const STAGES: readonly Stage[] = ['kids', 'middle', 'teen', 'classic'];

export type Learner = { id: string; name: string; is_self: boolean; stage: Stage };
export type Learners = { data: Learner[]; viewer_stage: Stage };

/** A lesson as a family sees it: with whom, and what they may do with it. */
export type FamilyLesson = TeacherLesson & {
  can_cancel: boolean;
  can_refuse_substitute: boolean;
};

export type FamilyLessons = { learner: { id: string; name: string }; notices: string[]; data: FamilyLesson[] };

export type CancelPreview = { outcome: string; reasons: { value: string; label: string }[] };

export type Rating = {
  can_rate: boolean;
  why: string | null;
  stars: number | null;
  tags: string[];
  comment: string | null;
  choices: { good: { value: string; label: string }[]; bad: { value: string; label: string }[] };
};

/** Extra lessons the person can buy on a course they pay for; one invoice for them waits at a time. */
export type ExtraOffer = { price: string; price_minor: number; currency: string; minutes: number; max: number; waiting_invoice: { id: string; number: string } | null };

export type Balance = {
  learner: { id: string; name: string };
  data: { enrollment_id: string; subject: string | null; minutes: number; session_minutes: number | null; sessions: number | null; extra: ExtraOffer | null }[];
};

/** A line of an invoice: what it is for, and what came off it (a discount is negative). */
export type InvoiceLine = { kind: 'block' | 'extra' | 'discount' | string; description: string; quantity: number; amount_minor: number; amount: string };

export type Invoice = {
  id: string;
  number: string;
  for: string;
  total: string;
  outstanding: string;
  outstanding_minor: number;
  status: string;
  status_label: string;
  issued_at: LocalTime | null;
  due_at: LocalTime | null;
  paid_at: LocalTime | null;
  document_url: string | null;
  pay_with: { account_id: string; label: string; methods: string[] }[];
  /** What is left as a number to type ("400.00"). */
  outstanding_decimal: string;
  /** The latest proof of paying by hand: pending, approved, or rejected with the academy's reason. */
  proof: { id: string; status: 'pending' | 'approved' | 'rejected'; text: string; reason: string | null } | null;
  can_send_proof: boolean;
  lines: InvoiceLine[];
};

export type ManualPayment = { note: string | null; takes_proofs: boolean; methods: { value: string; label: string }[] };

export type Invoices = { owed: string | null; manual: ManualPayment; data: Invoice[] };

type Rank = { key: string; points: number; next: number | null; next_key: string | null; progress: number; label: string; next_label: string | null };

export type JourneyLesson = { id: string | null; title: string | null; state: string | null };

export type Course = {
  enrollment_id: string;
  subject: string;
  teacher: string | null;
  program: string | null;
  /** null when the course has no curriculum. */
  percent: number | null;
  finished: boolean;
  lessons_per_week: number | null;
  finish_on: LocalTime | null;
  stars: number;
  streak: number;
  points: number;
  /** done, here, ahead (a level); each lesson done, here, review or todo. */
  worlds: { index: number; title: string; percent: number; state: string; done: number; lessons: JourneyLesson[] }[];
  level: { index: number; title: string; count: number; weeks_left: number | null } | null;
  next: JourneyLesson[];
};

export type Journey = {
  learner_id: string;
  stars: number;
  attended: number;
  on_time: number;
  streak: number;
  points: number;
  rank: Rank;
  badges: { key: string; earned: boolean; name: string; hint: string }[];
  courses: Course[];
};

export type Challenge = {
  key: string;
  period: string;
  /** week, last_week, month, last_month or world (a level's boss). */
  group: string;
  title: string;
  hint: string | null;
  /** calendar, homework, clock, star, bolt, trophy, crown or chest. */
  icon: string;
  target: number;
  progress: number;
  /** active, missed, done (ready to collect) or claimed. */
  state: string;
  state_label: string;
  claimable: boolean;
  reward: number;
  reward_label: string;
  /** The kids' chest: the sticker it holds. */
  sticker: { key: string; name: string } | null;
  world: { index: number; title: string; subject?: string } | null;
  ends_at: LocalTime | null;
};

/** What collecting answers: the reward, a sticker from a chest, and whether the rank went up. */
export type Claimed = { reward: number; reward_label: string; sticker: { key: string; name: string } | null; points_before: number; points: number; ranked_up: boolean; rank: Rank };

export type Challenges = {
  learner_id: string;
  stage: Stage;
  /** Only the student collects rewards, never a guardian. */
  can_claim: boolean;
  week: { starts_at: LocalTime; ends_at: LocalTime };
  points: number;
  rank: Rank;
  challenges: Challenge[];
  stickers: { earned: number; items: { key: string; earned: boolean; name: string }[] } | null;
  cheers: { id: string; kind: string; from: string; at: LocalTime }[];
  cheers_left: number | null;
};

export type LearnerCourses = {
  data: {
    id: string;
    name: string;
    enrollments: {
      id: string;
      subject: string;
      teacher: string | null;
      program: string | null;
      percent: number | null;
      current: { id: string; title: string; path: string[] } | null;
      eta_weeks: number | null;
      homework_open: number;
    }[];
  }[];
};

export type FamilyReport = {
  id: string;
  kind: 'lesson' | 'absence';
  lesson_at: LocalTime | null;
  submitted_at: LocalTime | null;
  engagement: number | null;
  overall: number | null;
  notes_for_student: string | null;
  absence_note: string | null;
  teacher: { id: string; name: string } | null;
  items: { node_id: string; title: string | null; outcome: string; outcome_label: string; score: number | null; note: string | null }[];
};

export type { Homework, Roadmap };

/** A guardian's consent to a child's account (GET /v1/family/consents). */
export type ChildConsent = {
  student: { id: string; name: string };
  state: 'waiting' | 'granted';
  granted_at: LocalTime | null;
  by: string | null;
  can_withdraw: boolean;
  text: { version: number; title: string; intro: string; sections: { title: string; body: string }[]; agree: string };
};
export type Consents = { age: number; children: ChildConsent[] };

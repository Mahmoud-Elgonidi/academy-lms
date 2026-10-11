import type { LocalTime } from '../time';
import type { ReportAnswer } from '../report-answers';
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
  /** One per balance: a course's own, or a shared package's (shared: all its subjects in subject). */
  data: { enrollment_id: string; subject: string | null; shared: boolean; minutes: number; session_minutes: number | null; sessions: number | null; extra: ExtraOffer | null }[];
};

/** A line of an invoice: what it is for, and what came off it (a discount is negative). */
export type InvoiceLine = { kind: 'block' | 'extra' | 'discount' | string; description: string; quantity: number; amount_minor: number; amount: string };

export type Invoice = {
  id: string;
  number: string;
  for: string;
  /** One invoice for several children's renewals that fell due in the same week. */
  family: boolean;
  student_ids: string[];
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
  /** Answers to the academy's own questions (only those the family reads). */
  answers: ReportAnswer[];
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

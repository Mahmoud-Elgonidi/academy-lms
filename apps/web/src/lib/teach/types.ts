import type { LocalTime } from '../time';
import type { ReportAnswer, ReportTemplate } from '../report-answers';

/**
 * What the teacher endpoints answer (the API's presenters), as the pages read
 * them. Labels arrive in the reader's language and times as LocalTime, so the
 * pages show them as written.
 */

export type Lesson = {
  id: string;
  starts_at: LocalTime;
  ends_at: LocalTime;
  minutes: number;
  subject: string;
  /** The student (for a teacher). */
  with: string;
  /** scheduled, in_progress, completed, student_absent, teacher_absent, needs_review, cancelled. */
  status: string;
  status_label: string;
  /** regular, trial, makeup, extra, assessment. */
  type: string;
  type_label: string | null;
  unfunded: boolean;
  join: { open: boolean; opens_at: LocalTime | null };
  substitute_for: string | null;
  cancelled_because: string | null;
};

/** GET teacher/lessons: days on the teacher's calendar. */
export type LessonDays = { from: string; to: string; count: number; minutes: number; data: Lesson[] };

export type Person = { id: string; name: string };

export type ReportSummary = {
  id: string;
  session_id: string;
  enrollment_id: string | null;
  /** lesson or absence. */
  kind: 'lesson' | 'absence';
  /** due, overdue, submitted, reviewed. */
  status: 'due' | 'overdue' | 'submitted' | 'reviewed';
  status_label: string;
  student: Person | null;
  lesson_at: LocalTime | null;
  due_at: LocalTime | null;
  submitted_at: LocalTime | null;
  late: boolean;
};

export type Outcome = 'introduced' | 'practiced' | 'mastered' | 'needs_review';
export const OUTCOMES: readonly Outcome[] = ['introduced', 'practiced', 'mastered', 'needs_review'];

export type ReportItem = {
  node_id: string;
  title: string;
  path: string;
  /** next or review: why today's plan has it (null when added by hand). */
  reason: 'next' | 'review' | null;
  objectives: string | string[] | null;
  outcome: Outcome | null;
  score: number | null;
  note: string | null;
};

export type Accepts = 'any' | 'text' | 'audio' | 'file';
export const ACCEPTS: readonly Accepts[] = ['any', 'text', 'audio', 'file'];

export type Submission = {
  id: string;
  body: string | null;
  file: { name: string; mime: string; size: number; is_audio: boolean } | null;
  submitted_at: LocalTime | null;
};

export type Homework = {
  id: string;
  report_id: string | null;
  student: Person | null;
  title: string;
  instructions: string | null;
  accepts: Accepts;
  /** assigned, submitted, reviewed, missed. */
  status: string;
  status_label: string;
  due_at: LocalTime | null;
  grade: number | null;
  feedback: string | null;
  reviewed_at: LocalTime | null;
  submissions: Submission[];
};

export type Roadmap = {
  program: { id: string; name: string };
  percent: number;
  lessons: { mastered: number; total: number };
  pace: number | null;
  eta_weeks: number | null;
  current: { id: string; title: string; path: string[] } | null;
  finished: boolean;
  levels: {
    id: string;
    title: string;
    percent: number;
    has_current: boolean;
    units: { id: string; title: string; percent: number; lessons: RoadmapLesson[] }[];
    lessons: RoadmapLesson[];
  }[];
};

export type RoadmapLesson = {
  id: string;
  title: string;
  /** done, here, todo (and review when it slipped back). */
  state: string;
  times_seen: number;
  last_outcome: Outcome | null;
  last_score: number | null;
  /** The lesson's files and links; a file's url is signed for twelve hours and opens without signing in. */
  materials?: Material[];
};

export type Material = {
  id: string;
  /** pdf, audio, video, image or link. */
  type: 'pdf' | 'audio' | 'video' | 'image' | 'link';
  title: string;
  url: string;
  /** Teachers only (a guide, the answers): never in a family's roadmap. */
  teachers_only: boolean;
  size: number | null;
};

/** GET teacher/reports/{id}: the report with today's plan, lessons that can be added, and the homework given with it. */
export type ReportForm = ReportSummary & {
  engagement: number | null;
  overall: number | null;
  notes_for_student: string | null;
  notes_internal: string | null;
  absence_note: string | null;
  review_note: string | null;
  items: ReportItem[];
  lesson_options: { id: string; label: string }[];
  homework: Homework | null;
  roadmap: Roadmap | null;
  /** The subject's own questions; null when it has none. */
  template: ReportTemplate | null;
  answers: ReportAnswer[];
};

export type Student = {
  enrollment_id: string;
  student_id: string;
  student: string;
  subject: string;
  paused: boolean;
  percent: number | null;
  here: string | null;
  to_mark: number;
  last_report_at: LocalTime | null;
};

export type Trial = {
  id: string;
  session_id: string | null;
  name: string;
  age: string | null;
  subject: string;
  starts_at: LocalTime;
  notes: string | null;
  level: string | null;
  plan_id: string | null;
  plan: string | null;
  per_week: number | null;
  assessment: string | null;
};

export type Trials = { to_assess: Trial[]; upcoming: Trial[]; done: Trial[]; plans: { id: string; name: string }[] };

export type ShiftBlock = { weekday: number; start: string; end: string };
export type ShiftWeek = { timezone: string; weekly_minutes?: number; blocks: ShiftBlock[] };

export type ShiftProposal = {
  id: string;
  /** draft, submitted, changes_requested, countered (approved, rejected, withdrawn once closed). */
  status: string;
  status_label: string;
  /** admin or teacher: whose turn it is. */
  waiting_on: 'admin' | 'teacher' | null;
  effective_from: string;
  note: string | null;
  min_weekly_minutes: number | null;
  max_weekly_minutes: number | null;
  week: ShiftWeek;
  /** The version before the academy's counter-offer, to show what changed. */
  previous_week: ShiftWeek | null;
  comments: { mine: boolean; body: string; at: LocalTime | null }[];
};

export type Shifts = {
  timezone: string;
  current: ShiftWeek;
  proposal: ShiftProposal | null;
  earliest_start: { adding_time: string; removing_time: string };
};

export type TimeOff = {
  id: string;
  period: string;
  reason: string;
  reason_label: string;
  note: string | null;
  status: 'pending' | 'approved' | 'rejected' | 'withdrawn';
  status_label: string;
  decision_note: string | null;
  can_withdraw: boolean;
  cover: { total: number; covered: number } | null;
};

export type TimeOffList = { data: TimeOff[]; holidays: { name: string; period: string }[] };

type Money = { minor: number; currency: string; formatted: string };

export type Earnings = {
  month: string;
  label: string;
  closed: boolean;
  pending: number;
  unpriced: number;
  totals: { currency: string; lessons: number; hours: string; lesson_pay: Money; bonuses: Money; deductions: Money; total: Money }[];
  lessons: {
    key: string;
    when: string;
    student: string;
    subject: string;
    outcome: string;
    share: string;
    rate: string | null;
    amount: string;
    negative: boolean;
    corrected: boolean;
    notes: string[];
  }[];
  adjustments: { kind: string; note: string; amount: string; negative: boolean; at: string; reversed?: boolean }[];
};

export type Performance = {
  days: number;
  kpis: { key: string; label: string; value: string; target: string | null; n: number; state: 'good' | 'bad' | 'none'; hint: string | null; group: string }[];
  events: { session: string; kind: string; text: string; lesson: string; comment: string | null; excluded: string | null; note: string | null }[];
  ratings: { avg: string | null; count: number; list: { stars: number; tags: string[]; comment: string | null; lesson: string }[] };
};

export type Notification = {
  id: string;
  title: string;
  body: string;
  action: { screen: string; id: string | null } | null;
  urgent: boolean;
  read: boolean;
  created_at: LocalTime;
};

/** POST lessons/{id}/join: a seat in the lesson's room. */
export type JoinTicket = {
  /** livekit, or external (an outside link in url). */
  provider: string;
  url: string;
  token: string | null;
  room: string | null;
  identity: string;
  name: string;
  role: string;
  can_publish: boolean;
  expires_at: string;
};

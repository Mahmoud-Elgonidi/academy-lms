import type { LocalTime } from '../time';

/**
 * What the Quran program's endpoints answer (docs/architecture/02, section
 * 26), as the pages read them: a lesson's portion and its record for the
 * teacher, a student's plan and pace, the examiner's queue, and a learner's
 * plans, exams and certificates for the family. Passages come as surah and
 * verses with a title the API writes in the reader's language; the Quran's
 * own words never come here (the shared mushaf carries them).
 */

/** The three parts of a lesson's portion. */
export type PartKey = 'new' | 'near' | 'far';

/** excellent, very_good, good, acceptable or repeat (best first). */
export type GradeKey = 'excellent' | 'very_good' | 'good' | 'acceptable' | 'repeat';

export type VerseRange = { surah: number; from: number; to: number; title: string };

export type PortionPart = { label: string; ranges: VerseRange[]; pages: number[] };

export type Stop = { kind: string; number: number | null; label: string };

export type Portion = {
  new: PortionPart | null;
  near: PortionPart | null;
  far: PortionPart | null;
  /** On the Noorani Qaida: the lesson's number and title. */
  lesson: { number: number | null; title: string } | null;
  /** The exam stops the new part completes. */
  stops: Stop[];
  finished: boolean;
};

export type Program = {
  id: string;
  name: string;
  track: string;
  track_label: string;
  direction: string;
  unit: string;
  unit_label: string;
  pass_grade: string;
  exam_stops: string;
};

export type Pace = { new_amount: number; near_portions: number; far_pages: number };

export type Plan = {
  id: string;
  enrollment_id: string | null;
  group_id: string | null;
  program: Program;
  pace: Pace;
  /** Where the next new portion starts; null once the program is complete. */
  position: { surah: number | null; verse: number; title: string } | null;
  finished: boolean;
  /** paused or ended: the plan waits where it is. */
  frozen: 'paused' | 'ended' | null;
  frozen_label: string | null;
  /** How much of the program's scope is behind the learner, 0 to 1. */
  progress: number;
  portion: Portion | null;
};

export type MistakeType = { key: string; label: string; weight: number };

export type Grade = { key: GradeKey; label: string };

export type MistakeCount = { kind: string; label: string; count: number };

/** One part as recorded: its grade, and the mistakes by kind (none in the kids' look); the teacher also reads the computed grade and their note. */
export type ResultPart = {
  label: string;
  ranges: VerseRange[];
  lesson: { number: number | null; title: string } | null;
  grade: GradeKey;
  grade_label: string;
  mistakes: MistakeCount[];
  computed?: GradeKey;
  note?: string | null;
};

export type LessonResult = {
  id: string;
  session_id: string;
  student_id: string;
  parts: Partial<Record<PartKey, ResultPart>>;
  /** Whether the position moved past the new part. */
  advanced: boolean;
  /** The course was paused or over: nothing moved. */
  frozen: boolean;
  recorded_at: LocalTime | null;
};

/** GET quran/lessons/{session}/portion. */
export type LessonPortion = {
  session_id: string;
  group: boolean;
  students: { id: string; name: string }[];
  plan: Plan | null;
  mistake_types: MistakeType[];
  grades: Grade[];
  results: LessonResult[];
};

/** GET quran/students/{enrollment}/plan. */
export type StudentPlan = {
  enrollment_id: string;
  plan: Plan | null;
  recent: LessonResult[];
  grades: Grade[];
  mistake_types: MistakeType[];
};

/** GET quran/plan: each Quran course of the learner with a plan. */
export type FamilyPlans = {
  learner_id: string;
  courses: { enrollment_id: string; subject: string; plan: Plan; recent: LessonResult[] }[];
};

export type ExamStatus = 'suggested' | 'booked' | 'passed' | 'failed' | 'cancelled';

export type Exam = {
  id: string;
  student_id: string;
  /** The student's name (the examiner's queue; null for a family). */
  student: string | null;
  enrollment_id: string;
  program: { id: string; name: string };
  stop: Stop;
  status: ExamStatus;
  status_label: string;
  retake: boolean;
  /** A retake is not booked before this day (YYYY-MM-DD). */
  not_before: string | null;
  examiner: { id: string; name: string | null } | null;
  lesson: { session_id: string; status: string; starts_at: LocalTime | null; ends_at: LocalTime | null } | null;
  result: {
    grade: GradeKey;
    grade_label: string;
    passed: boolean;
    mistakes: MistakeCount[];
    note: string | null;
    retake_on: string | null;
    recorded_at: LocalTime | null;
    computed?: GradeKey;
  } | null;
};

/** GET quran/examiner/exams. */
export type ExaminerQueue = { booked: Exam[]; recent: Exam[]; mistake_types: MistakeType[]; grades: Grade[] };

/** GET quran/exams. */
export type FamilyExams = { learner_id: string; exams: Exam[] };

export type Certificate = {
  id: string;
  number: string;
  title: string;
  portion: Stop;
  riwaya: string;
  grade: string;
  grade_label: string;
  examiner: string | null;
  issued_at: LocalTime | null;
  revoked: boolean;
  /** Its page to read and print, signed for this reader; null once revoked. */
  url: string | null;
  /** Its public verification code (XXXX-XXXX-XXXX-XXXX); null once revoked. */
  code: string | null;
};

/** GET quran/certificates: shown false while the academy keeps certificates from families. */
export type FamilyCertificates = { learner_id: string; shown: boolean; certificates: Certificate[] | Record<string, Certificate> };

/** GET quran/certificates/{code}: what anyone may read of a certificate. */
export type VerifiedCertificate = {
  number: string;
  title: string;
  portion: Stop;
  riwaya: string;
  grade: string;
  grade_label: string;
  issued_at: LocalTime | null;
  academy: string;
  /** The student's first name and the initial of their last. */
  student: string;
  revoked: boolean;
  revoked_at: LocalTime | null;
};

/** The record of one part a teacher or examiner sends. */
export type PartRecord = { mistakes: { kind: string; count: number }[] | null; grade: GradeKey | null; note: string | null };

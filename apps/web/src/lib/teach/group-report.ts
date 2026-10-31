/**
 * A group lesson's report as the teacher writes it (docs/architecture/02,
 * section 25; the API's SubmitGroupReport): the class's shared part once
 * (what was covered, the questions, the homework, the note for the academy)
 * and a row for each student: focus, overall and a note for one who came,
 * the absence note for one who did not. Every student's row goes in, all or
 * none; a row a supervisor reviewed stays as it was (sent with its id only).
 * Pure, so the form's checks and the API's answers per row are tested here.
 */

/** A student's own report in the lesson, as GET teacher/reports/{id} lists them in `group.students`. */
export type GroupStudent = {
  report_id: string;
  student_id: string;
  name: string;
  /** lesson (came: scores and a note) or absence (a note for the family). */
  kind: 'lesson' | 'absence';
  status: string;
  engagement: number | null;
  overall: number | null;
  notes_for_student: string | null;
  absence_note: string | null;
};

/** A row of the form. */
export type GroupRow = {
  studentId: string;
  name: string;
  kind: 'lesson' | 'absence';
  /** A supervisor reviewed it: shown, not changed. */
  locked: boolean;
  engagement: number | null;
  overall: number | null;
  note: string;
  absenceNote: string;
};

/** What a row is missing before it can go. */
export type RowProblem = 'ratings' | 'absence_note';

export function rowsOf(students: readonly GroupStudent[]): GroupRow[] {
  return students.map((s) => ({
    studentId: s.student_id,
    name: s.name,
    kind: s.kind === 'absence' ? 'absence' : 'lesson',
    locked: s.status === 'reviewed',
    engagement: s.engagement,
    overall: s.overall,
    note: s.notes_for_student ?? '',
    absenceNote: s.absence_note ?? '',
  }));
}

/** Whether the shared lesson part (the plan, the questions) is asked for: someone came, and their report is still the teacher's. */
export function sharedLessonNeeded(rows: readonly GroupRow[]): boolean {
  return rows.some((r) => r.kind === 'lesson' && !r.locked);
}

/** Each row's missing parts, by student (rows with nothing missing left out). */
export function rowProblems(rows: readonly GroupRow[]): Record<string, RowProblem[]> {
  const found: Record<string, RowProblem[]> = {};
  for (const row of rows) {
    if (row.locked) continue;
    const missing: RowProblem[] = [];
    if (row.kind === 'lesson' && (row.engagement === null || row.overall === null)) missing.push('ratings');
    if (row.kind === 'absence' && row.absenceNote.trim() === '') missing.push('absence_note');
    if (missing.length > 0) found[row.studentId] = missing;
  }
  return found;
}

/** A row as the API takes it in `students`, in the rows' order (the API names a row's errors by that place). */
export type StudentPayload = {
  student_id: string;
  engagement?: number | null;
  overall?: number | null;
  notes_for_student?: string | null;
  absence_note?: string | null;
};

export function studentsPayload(rows: readonly GroupRow[]): StudentPayload[] {
  return rows.map((row) => {
    if (row.locked) return { student_id: row.studentId };
    if (row.kind === 'absence') return { student_id: row.studentId, absence_note: row.absenceNote.trim() };
    return { student_id: row.studentId, engagement: row.engagement, overall: row.overall, notes_for_student: row.note.trim() || null };
  });
}

/** The fields of a row the API may refuse (SubmitGroupReport::ROW). */
export const ROW_FIELDS = ['engagement', 'overall', 'notes_for_student', 'absence_note'] as const;

/**
 * The API's words for each row ("students.2.engagement" is the third row's
 * focus), by student and field; anything else stays for the form.
 */
export function rowErrors(errors: Record<string, string[]>, rows: readonly GroupRow[]): Record<string, Record<string, string>> {
  const found: Record<string, Record<string, string>> = {};
  for (const [key, messages] of Object.entries(errors)) {
    const match = /^students\.(\d+)\.(\w+)$/.exec(key);
    const row = match ? rows[Number(match[1])] : undefined;
    const message = messages[0];
    if (!match || !row || !message) continue;
    (found[row.studentId] ??= {})[match[2]!] = message;
  }
  return found;
}

/** A report as the list reads it: whose, for which lesson, and (a group lesson) which group. */
type ListedReport = {
  id: string;
  session_id: string;
  kind: 'lesson' | 'absence';
  status: 'due' | 'overdue' | 'submitted' | 'reviewed';
  student: { id: string; name: string } | null;
  late: boolean;
  format?: 'private' | 'group';
  group_id?: string | null;
};

/** A line of the reports list: one student's report, or a group lesson's class (its students together). */
export type ReportRow<R extends ListedReport> = {
  /** The report the line opens: for a class, one of a student who came (it carries the lesson's plan). */
  report: R;
  /** A group lesson: everyone in it, in the API's order; null for a one-to-one lesson. */
  students: { id: string; name: string }[] | null;
  /** The most pressing of the class's reports (overdue first): its state and label are the line's. */
  pressing: R;
  status: R['status'];
  late: boolean;
};

const urgency = { overdue: 0, due: 1, submitted: 2, reviewed: 3 } as const;

/**
 * The reports list's lines: a group lesson's reports (one per student,
 * sharing its session) become one line that opens the class form; the others
 * stay one a line. The list's order is kept, a class where its first report was.
 */
export function reportRows<R extends ListedReport>(reports: readonly R[]): ReportRow<R>[] {
  // Each line's reports, in the list's order: a class under its session, a one-to-one report alone.
  const lines = new Map<string, R[]>();
  for (const r of reports) {
    const key = r.group_id || r.format === 'group' ? `class:${r.session_id}` : `report:${r.id}`;
    const members = lines.get(key);
    if (members) members.push(r);
    else lines.set(key, [r]);
  }
  return [...lines.entries()].map(([key, members]) => {
    const first = members[0]!;
    if (key.startsWith('report:')) return { report: first, students: null, pressing: first, status: first.status, late: first.late };
    const opens = members.find((m) => m.kind === 'lesson' && m.status !== 'reviewed') ?? members.find((m) => m.kind === 'lesson') ?? first;
    const pressing = members.reduce((worst, m) => (urgency[m.status] < urgency[worst.status] ? m : worst), first);
    return {
      report: opens,
      students: members.flatMap((m) => (m.student ? [m.student] : [])),
      pressing,
      status: pressing.status,
      late: members.some((m) => m.late),
    };
  });
}
